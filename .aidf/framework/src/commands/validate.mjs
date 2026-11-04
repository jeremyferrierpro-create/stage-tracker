import { parseArgs, assertEnum } from '../util/args.mjs';
import { resolveProjectRoot, isInitialized, PHASES } from '../core/paths.mjs';
import { runGate, loadGates, GATE_STATUSES } from '../core/workflow.mjs';
import { attachLogFile } from '../util/logger.mjs';

export function validateArgSpec() {
  return {
    booleans: ['json', 'allow-exec', 'help', 'list'],
    aliases: { h: 'help', t: 'target', p: 'phase' },
  };
}

export async function validateCommand(argv, ctx) {
  const args = parseArgs(argv, validateArgSpec());
  if (args.help) {
    ctx.logger.info('Usage: aidf validate --phase <phase> [--kind entry|exit] [--allow-exec] [--json]');
    ctx.logger.info('       aidf validate --gate <gate-id> [--allow-exec]');
    return 0;
  }
  const root = resolveProjectRoot({ target: args.target, cwd: process.cwd() });
  if (!isInitialized(root)) {
    ctx.logger.error(`AIDF is not initialized in ${root}. Run "aidf install" first.`);
    return 1;
  }
  await attachLogFile(ctx.logger, root).catch(() => null);
  const gates = await loadGates(ctx.frameworkRoot);

  if (args.list) {
    if (ctx.json) process.stdout.write(`${JSON.stringify(gates, null, 2)}\n`);
    else {
      ctx.logger.table(
        gates.map((g) => ({ id: g.id, phase: g.phase, kind: g.kind, checks: (g.checks ?? []).length })),
        [
          { key: 'id', label: 'Gate' },
          { key: 'phase', label: 'Phase' },
          { key: 'kind', label: 'Kind' },
          { key: 'checks', label: 'Checks' },
        ],
      );
    }
    return 0;
  }

  let gateId = args.gate;
  if (!gateId) {
    const phase = assertEnum(args.phase, PHASES, 'phase');
    if (!phase) {
      ctx.logger.error('Missing --phase or --gate. Use --list to see gates.');
      return 1;
    }
    const kind = assertEnum(args.kind ?? 'exit', ['entry', 'exit'], 'kind');
    gateId = `${phase}-${kind}`;
  }

  const report = await runGate(root, gateId, {
    frameworkRoot: ctx.frameworkRoot,
    allowExec: Boolean(args['allow-exec']),
  });

  if (ctx.json) {
    process.stdout.write(`${JSON.stringify(report, null, 2)}\n`);
  } else {
    ctx.logger.info(`Gate ${report.gate} (${report.phase}/${report.kind}) → ${report.status}`);
    ctx.logger.table(
      report.checks.map((c) => ({
        id: c.id,
        status: c.status,
        blocking: c.blocking ? 'yes' : 'no',
        reason: c.reason,
      })),
      [
        { key: 'id', label: 'Check' },
        { key: 'status', label: 'Status' },
        { key: 'blocking', label: 'Blocking' },
        { key: 'reason', label: 'Reason' },
      ],
    );
    if (report.evidence) ctx.logger.info(`Evidence: ${report.evidence.file} (${report.evidence.hash.slice(0, 12)})`);
    if (report.status !== 'PASS') {
      ctx.logger.warn('A phase is not complete unless its exit gate is PASS with evidence.');
    }
  }
  return report.status === 'PASS' ? 0 : 1;
}
