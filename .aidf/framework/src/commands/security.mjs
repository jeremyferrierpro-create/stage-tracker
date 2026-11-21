import path from 'node:path';
import { parseArgs } from '../util/args.mjs';
import { resolveProjectRoot, isInitialized, aidfPaths } from '../core/paths.mjs';
import { attachLogFile } from '../util/logger.mjs';
import { writeAtomic, ensureDir, resolveInside, toPosix } from '../util/fsx.mjs';
import {
  generateThreatModel, saveThreatModel, loadThreatModel, renderThreatModelMarkdown,
} from '../core/security/threat-model.mjs';
import { validateArtefact } from '../core/artefacts/index.mjs';

export function securityArgSpec() {
  return {
    booleans: ['json', 'help', 'write', 'markdown'],
    aliases: { h: 'help', t: 'target' },
  };
}

function printSummary(ctx, tm) {
  const open = (tm.threats ?? []).filter((t) => t.status === 'open');
  const mitigated = (tm.threats ?? []).filter((t) => t.status === 'mitigated');
  ctx.logger.info(`Threat model ${tm.id} — ${tm.threats?.length ?? 0} threats (${open.length} open, ${mitigated.length} mitigated)`);
  for (const t of tm.threats ?? []) {
    ctx.logger.info(`  [${t.status}] ${t.id} ${t.name} (${t.stride})`);
  }
  if (open.length > 0) ctx.logger.warn('Open threats require mitigation or explicit human acceptance.');
  ctx.logger.warn('Generated model is DRAFT; human review required before VALIDATED.');
}

export async function securityCommand(argv, ctx) {
  const args = parseArgs(argv, securityArgSpec());
  const sub = args._[0] ?? 'analyze';
  if (args.help || sub === 'help') {
    ctx.logger.info('Usage: aidf security <analyze|show> [--write] [--markdown] [--out <file>] [--json]');
    return 0;
  }
  const root = resolveProjectRoot({ target: args.target, cwd: process.cwd() });
  if (!isInitialized(root)) {
    ctx.logger.error(`AIDF is not initialized in ${root}. Run "aidf install" first.`);
    return 1;
  }
  await attachLogFile(ctx.logger, root).catch(() => null);

  if (sub === 'show') {
    const tm = await loadThreatModel(root);
    if (!tm) {
      ctx.logger.error('No threat model found. Run "aidf security analyze --write" first.');
      return 1;
    }
    if (ctx.json) {
      process.stdout.write(`${JSON.stringify(tm, null, 2)}\n`);
    } else {
      printSummary(ctx, tm);
    }
    return 0;
  }

  if (sub !== 'analyze') {
    ctx.logger.error(`Unknown security subcommand: ${sub}`);
    return 1;
  }

  const tm = await generateThreatModel(root);
  const validation = await validateArtefact(tm);
  if (!validation.valid) {
    ctx.logger.error(`Generated threat model failed validation: ${validation.errors.join('; ')}`);
    return 1;
  }

  let artefactPath = null;
  if (args.write) {
    artefactPath = await saveThreatModel(root, tm);
  }

  if (args.out || args.markdown) {
    try {
      const abs = args.out
        ? (path.isAbsolute(args.out) ? args.out : resolveInside(root, args.out))
        : path.join(aidfPaths(root).reports, 'THREAT-MODEL.md');
      await ensureDir(path.dirname(abs));
      await writeAtomic(abs, renderThreatModelMarkdown(tm));
      if (!args.out) ctx.logger.info(`Markdown report: ${toPosix(abs)}`);
    } catch (error) {
      ctx.logger.error(`Cannot write markdown: ${error.message}`);
      return 1;
    }
  }

  if (ctx.json) {
    process.stdout.write(`${JSON.stringify({ threatModel: tm, artefact: artefactPath ? toPosix(artefactPath) : null }, null, 2)}\n`);
    return 0;
  }

  printSummary(ctx, tm);
  if (artefactPath) ctx.logger.info(`Artefact: ${toPosix(artefactPath)}`);
  else ctx.logger.info('Not persisted. Re-run with --write to store under .aidf/artefacts/security/.');
  return 0;
}
