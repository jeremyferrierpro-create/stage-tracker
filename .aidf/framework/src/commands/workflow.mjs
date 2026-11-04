import { parseArgs, assertEnum } from '../util/args.mjs';
import { resolveProjectRoot, isInitialized, PHASES } from '../core/paths.mjs';
import {
  workflowStatus, enterPhase, exitPhase, loadWorkflowState, saveWorkflowState,
  loadPhases, nextPhase,
} from '../core/workflow.mjs';
import { attachLogFile } from '../util/logger.mjs';

export function workflowArgSpec() {
  return {
    booleans: ['json', 'allow-exec', 'yes', 'help'],
    aliases: { h: 'help', t: 'target' },
  };
}

function printGate(ctx, report) {
  ctx.logger.info(`Gate ${report.gate} (${report.kind}) → ${report.status}`);
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
  if (report.evidence) ctx.logger.info(`Evidence: ${report.evidence.file}`);
}

export async function workflowCommand(argv, ctx) {
  const args = parseArgs(argv, workflowArgSpec());
  const sub = args._[0] ?? 'status';
  if (args.help) {
    ctx.logger.info('Usage: aidf workflow <status|enter <phase>|exit <phase>|next|reset> [--allow-exec] [--json]');
    return 0;
  }
  const root = resolveProjectRoot({ target: args.target, cwd: process.cwd() });
  if (!isInitialized(root)) {
    ctx.logger.error(`AIDF is not initialized in ${root}. Run "aidf install" first.`);
    return 1;
  }
  await attachLogFile(ctx.logger, root).catch(() => null);

  if (sub === 'status') {
    const status = await workflowStatus(root, { frameworkRoot: ctx.frameworkRoot });
    if (ctx.json) {
      process.stdout.write(`${JSON.stringify(status, null, 2)}\n`);
    } else {
      ctx.logger.info(`AIDF workflow — current phase: ${status.currentPhase}${status.completed ? ' (completed)' : ''}`);
      ctx.logger.table(
        status.phases.map((p) => ({ ...p, current: p.current ? '→' : '' })),
        [
          { key: 'current', label: '' },
          { key: 'phase', label: 'Phase' },
          { key: 'name', label: 'Name' },
          { key: 'entry', label: 'Entry gate' },
          { key: 'exit', label: 'Exit gate' },
        ],
      );
      ctx.logger.info('Run `aidf workflow enter <phase>` and `aidf validate --phase <phase> --kind exit --allow-exec`.');
    }
    return 0;
  }

  if (sub === 'enter') {
    const phase = assertEnum(args._[1], PHASES, 'phase');
    if (!phase) {
      ctx.logger.error(`Usage: aidf workflow enter <${PHASES.join('|')}>`);
      return 1;
    }
    const result = await enterPhase(root, phase, { frameworkRoot: ctx.frameworkRoot, allowExec: Boolean(args['allow-exec']) });
    if (ctx.json) process.stdout.write(`${JSON.stringify(result, null, 2)}\n`);
    else if (result.alreadyInPhase) ctx.logger.info(`Already in phase ${phase}.`);
    else {
      printGate(ctx, result.gate);
      if (result.status === 'PASS') ctx.logger.info(`Entered phase ${phase}.`);
      else ctx.logger.error(`Entry to ${phase} is ${result.status}. Fix the checks above.`);
    }
    return result.status === 'PASS' ? 0 : 1;
  }

  if (sub === 'exit') {
    const phase = assertEnum(args._[1], PHASES, 'phase');
    if (!phase) {
      ctx.logger.error(`Usage: aidf workflow exit <${PHASES.join('|')}>`);
      return 1;
    }
    const result = await exitPhase(root, phase, { frameworkRoot: ctx.frameworkRoot, allowExec: Boolean(args['allow-exec']) });
    if (ctx.json) process.stdout.write(`${JSON.stringify(result, null, 2)}\n`);
    else {
      printGate(ctx, result.gate);
      if (result.status === 'PASS') ctx.logger.info(`Exited ${phase}${result.nextPhase ? `; next phase: ${result.nextPhase}` : '; cycle complete'}.`);
      else ctx.logger.error(`Exit from ${phase} is ${result.status}. The phase is not complete.`);
    }
    return result.status === 'PASS' ? 0 : 1;
  }

  if (sub === 'next') {
    const state = await loadWorkflowState(root);
    const phases = await loadPhases(ctx.frameworkRoot);
    const next = nextPhase(phases, state.currentPhase);
    if (!next) {
      ctx.logger.info('No next phase; the cycle is complete.');
      return 0;
    }
    const result = await enterPhase(root, next, { frameworkRoot: ctx.frameworkRoot, allowExec: Boolean(args['allow-exec']) });
    if (ctx.json) process.stdout.write(`${JSON.stringify(result, null, 2)}\n`);
    else {
      printGate(ctx, result.gate);
      ctx.logger.info(result.status === 'PASS' ? `Entered ${next}.` : `Cannot enter ${next}: ${result.status}.`);
    }
    return result.status === 'PASS' ? 0 : 1;
  }

  if (sub === 'reset') {
    if (!args.yes) {
      ctx.logger.error('Refusing to reset workflow state without --yes.');
      return 1;
    }
    const state = await loadWorkflowState(root);
    state.currentPhase = 'intake';
    state.completed = false;
    state.gates = {};
    state.history.push({ ts: new Date().toISOString(), event: 'reset' });
    await saveWorkflowState(root, state);
    ctx.logger.info('Workflow state reset to intake. Evidence files were not deleted.');
    return 0;
  }

  ctx.logger.error(`Unknown workflow subcommand: ${sub}`);
  return 1;
}
