import path from 'node:path';
import { parseArgs, assertEnum } from '../util/args.mjs';
import { resolveProjectRoot, isInitialized, DOCUMENTS, DOC_STATUSES } from '../core/paths.mjs';
import { loadConfig, saveConfig, setDocumentStatus, defaultConfig } from '../core/config.mjs';
import { loadManifest, saveManifest, recordFile } from '../core/manifest.mjs';
import { readText, writeAtomic, sha256Text, exists } from '../util/fsx.mjs';
import { splitFrontmatter, joinFrontmatter } from '../util/frontmatter.mjs';
import { attachLogFile } from '../util/logger.mjs';
import { generateDocs } from '../core/docsgen.mjs';

export function docsArgSpec() {
  return {
    booleans: ['json', 'help', 'check'],
    aliases: { h: 'help', t: 'target' },
  };
}

export async function docsCommand(argv, ctx) {
  const args = parseArgs(argv, docsArgSpec());
  const sub = args._[0] ?? 'list';
  if (args.help) {
    ctx.logger.info('Usage: aidf docs <list|show|status|generate> [DOC] [STATUS] [--by <name>] [--note "..."] [--check]');
    return 0;
  }
  const root = resolveProjectRoot({ target: args.target, cwd: process.cwd() });
  if (!isInitialized(root)) {
    ctx.logger.error(`AIDF is not initialized in ${root}. Run "aidf install" first.`);
    return 1;
  }
  await attachLogFile(ctx.logger, root).catch(() => null);
  const config = await loadConfig(root).catch(() => defaultConfig({}));

  if (sub === 'list') {
    const rows = Object.entries(config.documents).map(([doc, entry]) => ({
      doc,
      status: entry.status,
      by: entry.validatedBy ?? '-',
      at: entry.validatedAt ? entry.validatedAt.slice(0, 10) : '-',
    }));
    if (ctx.json) process.stdout.write(`${JSON.stringify(rows, null, 2)}\n`);
    else {
      ctx.logger.info('AIDF documents');
      ctx.logger.table(rows, [
        { key: 'doc', label: 'Document' },
        { key: 'status', label: 'Status' },
        { key: 'by', label: 'Validated by' },
        { key: 'at', label: 'Date' },
      ]);
    }
    return 0;
  }

  if (sub === 'show') {
    const doc = assertEnum(args._[1], DOCUMENTS, 'document');
    if (!doc) {
      ctx.logger.error('Usage: aidf docs show <DOCUMENT>');
      return 1;
    }
    const file = path.join(root, '.aidf', 'docs', `${doc}.md`);
    if (!(await exists(file))) {
      ctx.logger.error(`Missing document file: .aidf/docs/${doc}.md`);
      return 1;
    }
    process.stdout.write(await readText(file));
    return 0;
  }

  if (sub === 'generate') {
    const result = await generateDocs(root, { check: Boolean(args.check) });
    if (ctx.json) {
      process.stdout.write(`${JSON.stringify(result, null, 2)}\n`);
    } else {
      ctx.logger.info(
        `${args.check ? 'Checked' : 'Generated'} ${result.generated.length} document(s) in ${result.directory}`,
      );
      for (const g of result.generated) {
        ctx.logger.info(`  ${g.action.padEnd(11)} ${g.file} (from ${g.sourceId})`);
        if (g.diverged) ctx.logger.warn(`    manual edit detected — regenerate overwrites it`);
      }
      if (result.generated.length === 0) {
        ctx.logger.warn('No artefacts found to render. Create requirements/test-plan/threat-model/decision artefacts first.');
      }
    }
    return 0;
  }

  if (sub === 'status') {
    const doc = assertEnum(args._[1], DOCUMENTS, 'document');
    const status = assertEnum(args._[2], DOC_STATUSES, 'status');
    if (!doc || !status) {
      ctx.logger.error(`Usage: aidf docs status <DOCUMENT> <${DOC_STATUSES.join('|')}> [--by <name>] [--note "..."]`);
      return 1;
    }
    if (status === 'VALIDATED' && !args.by) {
      ctx.logger.error('VALIDATED requires --by "<name>" to record human accountability.');
      return 1;
    }
    if (status === 'NOT_APPLICABLE' && !args.note) {
      ctx.logger.error('NOT_APPLICABLE requires --note "<reason>".');
      return 1;
    }
    setDocumentStatus(config, doc, status, { by: args.by, note: args.note });
    await saveConfig(root, config);
    const file = path.join(root, '.aidf', 'docs', `${doc}.md`);
    if (await exists(file)) {
      const text = await readText(file);
      const { frontmatter, body } = splitFrontmatter(text);
      const next = joinFrontmatter({
        ...(frontmatter ?? {}),
        doc,
        status,
        version: frontmatter?.version ?? '0.3.2',
        updated: new Date().toISOString().slice(0, 10),
        ...(args.by ? { validated_by: args.by } : {}),
      }, body);
      await writeAtomic(file, next);
      const manifest = await loadManifest(root);
      if (manifest.files[`.aidf/docs/${doc}.md`]) {
        recordFile(manifest, `.aidf/docs/${doc}.md`, {
          ...manifest.files[`.aidf/docs/${doc}.md`],
          sha256: sha256Text(next),
          updatedAt: new Date().toISOString(),
        });
        await saveManifest(root, manifest);
      }
    }
    ctx.logger.info(`${doc}: ${status}${args.by ? ` (by ${args.by})` : ''}`);
    return 0;
  }

  ctx.logger.error(`Unknown docs subcommand: ${sub}`);
  return 1;
}
