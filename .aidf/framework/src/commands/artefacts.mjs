import { parseArgs } from '../util/args.mjs';
import { resolveProjectRoot, isInitialized } from '../core/paths.mjs';
import { attachLogFile } from '../util/logger.mjs';
import {
  loadAllArtefacts, loadArtefact, verifyChain,
} from '../core/traceability/chain.mjs';
import { validateArtefact, resolveLinks } from '../core/artefacts/index.mjs';

export function artefactsArgSpec() {
  return {
    booleans: ['json', 'help', 'all'],
    aliases: { h: 'help', t: 'target' },
  };
}

function printJson(ctx, value) {
  process.stdout.write(`${JSON.stringify(value, null, 2)}\n`);
}

export async function artefactsCommand(argv, ctx) {
  const args = parseArgs(argv, artefactsArgSpec());
  const sub = args._[0] ?? 'list';
  if (args.help) {
    ctx.logger.info('Usage: aidf artefacts <list|show|validate|verify> [id] [--type t] [--status s] [--all]');
    return 0;
  }
  const root = resolveProjectRoot({ target: args.target, cwd: process.cwd() });
  if (!isInitialized(root)) {
    ctx.logger.error(`AIDF is not initialized in ${root}. Run "aidf install" first.`);
    return 1;
  }
  await attachLogFile(ctx.logger, root).catch(() => null);

  if (sub === 'list') {
    const artefacts = await loadAllArtefacts(root);
    let rows = [...artefacts.values()].map((a) => ({
      id: a.id,
      type: a.type ?? '-',
      status: a.status ?? 'DRAFT',
      updatedAt: a.updatedAt ?? '-',
    }));
    if (args.type) rows = rows.filter((r) => r.type === args.type);
    if (args.status) rows = rows.filter((r) => r.status === args.status);
    if (ctx.json) {
      printJson(ctx, { count: rows.length, artefacts: rows });
    } else {
      ctx.logger.info(`Artefacts (${rows.length})`);
      ctx.logger.table(rows, [
        { key: 'id', label: 'ID' },
        { key: 'type', label: 'Type' },
        { key: 'status', label: 'Status' },
        { key: 'updatedAt', label: 'Updated' },
      ]);
    }
    return 0;
  }

  if (sub === 'show') {
    const id = args._[1];
    if (!id) {
      ctx.logger.error('Usage: aidf artefacts show <id>');
      return 1;
    }
    const artefact = await loadArtefact(root, id);
    if (!artefact) {
      ctx.logger.error(`Artefact not found: ${id}`);
      return 1;
    }
    printJson(ctx, artefact);
    return 0;
  }

  if (sub === 'validate') {
    const targets = args.all
      ? [...(await loadAllArtefacts(root)).values()]
      : args._[1]
        ? [await loadArtefact(root, args._[1])].filter(Boolean)
        : null;
    if (!targets) {
      ctx.logger.error('Usage: aidf artefacts validate <id> | --all');
      return 1;
    }
    if (targets.length === 0) {
      ctx.logger.error('No artefacts found.');
      return 1;
    }
    const results = [];
    let failed = 0;
    for (const artefact of targets) {
      const validation = await validateArtefact(artefact);
      const links = await resolveLinks(root, artefact.links);
      const errors = [...validation.errors, ...links.errors];
      const warnings = [...validation.warnings];
      if (errors.length > 0) failed += 1;
      results.push({ id: artefact.id, valid: errors.length === 0, errors, warnings });
    }
    if (ctx.json) {
      printJson(ctx, { total: results.length, valid: results.filter((r) => r.valid).length, results });
    } else {
      for (const r of results) {
        ctx.logger.info(`${r.id}: ${r.valid ? 'VALID' : 'INVALID'}`);
        for (const e of r.errors) ctx.logger.error(`  - ${e}`);
        for (const w of r.warnings) ctx.logger.warn(`  - ${w}`);
      }
    }
    return failed === 0 ? 0 : 1;
  }

  if (sub === 'verify') {
    const id = args._[1];
    if (!id) {
      ctx.logger.error('Usage: aidf artefacts verify <id>');
      return 1;
    }
    const result = await verifyChain(root, id);
    if (ctx.json) {
      printJson(ctx, result);
    } else {
      ctx.logger.info(`Chain for ${id}: ${result.ok ? 'OK' : 'BROKEN'}`);
      ctx.logger.info(`Path: ${result.path.join(' -> ')}`);
      for (const b of result.brokenLinks) ctx.logger.error(`  - ${b}`);
      if (result.coverage) ctx.logger.info(`Coverage: ${JSON.stringify(result.coverage)}`);
    }
    return result.ok ? 0 : 1;
  }

  ctx.logger.error(`Unknown artefacts subcommand: ${sub}`);
  return 1;
}
