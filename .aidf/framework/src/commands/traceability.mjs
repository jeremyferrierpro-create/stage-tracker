import { parseArgs, assertEnum } from '../util/args.mjs';
import { resolveProjectRoot, isInitialized } from '../core/paths.mjs';
import { attachLogFile } from '../util/logger.mjs';
import {
  generateTraceabilityReport,
  verifyChain,
  findMissingLinks,
  findBrokenLinks,
  TRACEABILITY_TYPES,
} from '../core/traceability/chain.mjs';

export function traceabilityArgSpec() {
  return {
    booleans: ['json', 'help'],
    aliases: { h: 'help', t: 'target' },
  };
}

function printJson(value) {
  process.stdout.write(`${JSON.stringify(value, null, 2)}\n`);
}

export async function traceabilityCommand(argv, ctx) {
  const args = parseArgs(argv, traceabilityArgSpec());
  const sub = args._[0] ?? 'report';
  if (args.help) {
    ctx.logger.info('Usage: aidf traceability <report|verify|missing|broken> [id] [--type requirement|design|implementation|test|evidence]');
    return 0;
  }
  const root = resolveProjectRoot({ target: args.target, cwd: process.cwd() });
  if (!isInitialized(root)) {
    ctx.logger.error(`AIDF is not initialized in ${root}. Run "aidf install" first.`);
    return 1;
  }
  await attachLogFile(ctx.logger, root).catch(() => null);

  if (sub === 'report') {
    const report = await generateTraceabilityReport(root);
    if (ctx.json) {
      printJson(report);
    } else {
      ctx.logger.info(
        `Artefacts: ${report.summary.totalArtefacts} | `
        + `coverage ${report.coverage.overall.coveragePercentage}% `
        + `(${report.coverage.overall.fullyTraced} fully traced, `
        + `${report.coverage.overall.partiallyTraced} partial, `
        + `${report.coverage.overall.notTraced} untraced)`,
      );
      ctx.logger.info(`Links: ${report.links.total} | broken: ${report.broken.length}`);
      for (const rec of report.recommendations) ctx.logger.warn(rec);
    }
    return report.broken.length > 0 ? 1 : 0;
  }

  if (sub === 'verify') {
    const id = args._[1];
    if (!id) {
      ctx.logger.error('Usage: aidf traceability verify <artefact-id>');
      return 1;
    }
    const result = await verifyChain(root, id);
    if (ctx.json) {
      printJson(result);
    } else {
      ctx.logger.info(`Chain for ${id}: ${result.ok ? 'OK' : 'BROKEN'}`);
      ctx.logger.info(`Path: ${result.path.join(' -> ')}`);
      for (const b of result.brokenLinks) ctx.logger.error(`  - ${b}`);
      if (result.coverage) ctx.logger.info(`Coverage: ${JSON.stringify(result.coverage)}`);
    }
    return result.ok ? 0 : 1;
  }

  if (sub === 'missing') {
    const type = assertEnum(args.type, Object.keys(TRACEABILITY_TYPES), 'type');
    if (!type) {
      ctx.logger.error('Usage: aidf traceability missing --type requirement|design|implementation|test|evidence');
      return 1;
    }
    const missing = await findMissingLinks(root, { type });
    if (ctx.json) {
      printJson({ type, count: missing.length, missing });
    } else {
      ctx.logger.info(`Artefacts missing a '${type}' link: ${missing.length}`);
      ctx.logger.table(
        missing.map((m) => ({ id: m.id, type: m.type, missing: m.missing })),
        [
          { key: 'id', label: 'ID' },
          { key: 'type', label: 'Artefact type' },
          { key: 'missing', label: 'Missing link to' },
        ],
      );
    }
    return missing.length > 0 ? 1 : 0;
  }

  if (sub === 'broken') {
    const broken = await findBrokenLinks(root);
    if (ctx.json) {
      printJson({ count: broken.length, broken });
    } else {
      ctx.logger.info(`Broken links: ${broken.length}`);
      ctx.logger.table(
        broken.map((b) => ({ source: b.sourceId, type: b.linkType, target: b.targetId })),
        [
          { key: 'source', label: 'Source' },
          { key: 'type', label: 'Link type' },
          { key: 'target', label: 'Missing target' },
        ],
      );
    }
    return broken.length > 0 ? 1 : 0;
  }

  ctx.logger.error(`Unknown traceability subcommand: ${sub}`);
  return 1;
}
