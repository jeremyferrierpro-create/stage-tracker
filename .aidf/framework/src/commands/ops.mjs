import path from 'node:path';
import { parseArgs } from '../util/args.mjs';
import { resolveProjectRoot, isInitialized, aidfPaths } from '../core/paths.mjs';
import { attachLogFile } from '../util/logger.mjs';
import { writeAtomic, ensureDir, exists, toPosix } from '../util/fsx.mjs';
import { loadConfig, defaultConfig } from '../core/config.mjs';
import { recordEvidence } from '../core/evidence.mjs';
import {
  PIPELINE_TEMPLATE, loadPipeline, runPipeline, runRollback,
  recordLastRun, loadLastRun, writeOpsReport,
  watchPipeline, writeWatchReport, monitoringSteps,
} from '../core/ops.mjs';

export function opsArgSpec() {
  return {
    booleans: ['json', 'help', 'allow-exec', 'record', 'force'],
    strings: ['step', 'target', 'cycles', 'duration', 'interval'],
    aliases: { h: 'help' },
  };
}

function opsEnabled(config, args) {
  return Boolean(args['allow-exec'] ?? config?.security?.allowCommandExecution);
}

export async function opsCommand(argv, ctx) {
  const args = parseArgs(argv, opsArgSpec());
  if (args.help || args._[0] === 'help') {
    ctx.logger.info('Usage: aidf ops <init|plan|run|rollback|watch|status> [--allow-exec] [--step <id>] [--cycles <n> | --duration <s>] [--interval <s>] [--record] [--json]');
    return 0;
  }
  const root = resolveProjectRoot({ target: args.target, cwd: process.cwd() });
  if (!isInitialized(root)) {
    ctx.logger.error(`AIDF is not initialized in ${root}. Run "aidf install" first.`);
    return 1;
  }
  await attachLogFile(ctx.logger, root).catch(() => null);

  const sub = args._[0] ?? 'plan';
  const config = await loadConfig(root).catch(() => defaultConfig({}));

  if (sub === 'init') {
    const file = path.join(aidfPaths(root).workflows, 'pipeline.yaml');
    if (await exists(file) && !args.force) {
      ctx.logger.error(`${toPosix(file)} already exists. Pass --force to overwrite.`);
      return 1;
    }
    await ensureDir(aidfPaths(root).workflows);
    await writeAtomic(file, PIPELINE_TEMPLATE);
    ctx.logger.info(`Pipeline template written: ${toPosix(file)}`);
    ctx.logger.warn('Edit the commands before running: execution requires --allow-exec or security.allowCommandExecution=true.');
    return 0;
  }

  if (sub === 'status') {
    const last = await loadLastRun(root);
    const { pipeline, errors } = await loadPipeline(root);
    const out = {
      pipeline: { present: Boolean(pipeline), errors },
      lastRun: last,
    };
    if (ctx.json) {
      process.stdout.write(`${JSON.stringify(out, null, 2)}\n`);
    } else if (last) {
      ctx.logger.info(`Last ops run: ${last.runId} (${last.verdict}) at ${last.finishedAt}`);
      ctx.logger.info(`  deployStep: ${last.deployStep ?? '-'}  rollback steps: ${last.rollback?.length ?? 0}`);
    } else {
      ctx.logger.info('No ops run recorded yet.');
    }
    return 0;
  }

  if (sub === 'plan') {
    const { pipeline, errors, file } = await loadPipeline(root);
    if (!pipeline) {
      ctx.logger.error(errors[0] ?? `No pipeline found at ${toPosix(file)}. Run "aidf ops init".`);
      return 1;
    }
    if (errors.length) {
      for (const e of errors) ctx.logger.error(`  ${e}`);
      return 1;
    }
    ctx.logger.info(`Pipeline: ${toPosix(file)}`);
    for (const s of pipeline.steps) {
      ctx.logger.info(`  ${s.id} [${s.kind ?? 'custom'}] ${s.name ?? ''} — ${s.command}`);
    }
    ctx.logger.info(`  rollback steps: ${pipeline.rollback?.length ?? 0}`);
    ctx.logger.info(`  monitoring steps: ${monitoringSteps(pipeline).length}`);
    ctx.logger.info('Dry run only: no command was executed. Use "aidf ops run --allow-exec" to execute.');
    return 0;
  }

  if (sub === 'run') {
    const allowExec = opsEnabled(config, args);
    const run = await runPipeline(root, { allowExec, onlyStep: args.step });
    const files = await writeOpsReport(root, run, 'run');

    if (run.verdict === 'PASS') {
      await recordLastRun(root, run);
    }

    let evidence = null;
    if (args.record) {
      evidence = await recordEvidence(root, {
        type: 'ops-run',
        status: run.verdict === 'PASS' ? 'PASS' : run.verdict === 'NOT_PROVEN' ? 'NOT_PROVEN' : 'FAIL',
        summary: `Ops pipeline ${run.runId}: ${run.verdict} (${run.steps.length} step(s)${run.stoppedAt ? `, stopped at ${run.stoppedAt}` : ''}).`,
        createdBy: 'aidf',
        files: [files.json, files.markdown],
        checks: run.steps.map((s) => ({ id: s.id, status: s.status, reason: s.reason })),
        limitations: [
          'Pipeline commands are project-owned configuration; review pipeline.yaml before enabling execution.',
          'PASS means every configured command exited 0, not that the deployment is healthy.',
        ],
      });
    }

    if (ctx.json) {
      process.stdout.write(`${JSON.stringify({ run, files, evidence }, null, 2)}\n`);
    } else {
      ctx.logger.info(`Ops run ${run.runId}: ${run.verdict}`);
      for (const s of run.steps) {
        const line = `  ${s.id}: ${s.status}${s.exitCode != null ? ` (exit ${s.exitCode})` : ''}`;
        if (s.status === 'PASS') ctx.logger.info(line);
        else ctx.logger.error(`${line} — ${s.reason}`);
      }
      ctx.logger.info(`  report: ${files.markdown}`);
      if (evidence) ctx.logger.info(`  evidence: ${evidence.id}`);
      if (!allowExec) ctx.logger.warn('Execution was not permitted: pass --allow-exec or set security.allowCommandExecution=true.');
    }
    return run.verdict === 'PASS' ? 0 : run.verdict === 'INVALID' || run.verdict === 'ABSENT' ? 1 : 1;
  }

  if (sub === 'watch') {
    const allowExec = opsEnabled(config, args);
    const cycles = args.cycles !== undefined ? Number.parseInt(args.cycles, 10) : null;
    const durationMs = args.duration !== undefined ? Number.parseInt(args.duration, 10) * 1000 : null;
    const intervalSeconds = args.interval !== undefined ? Number.parseInt(args.interval, 10) : 60;
    if ((args.cycles !== undefined && (!Number.isInteger(cycles) || cycles < 1))
      || (args.duration !== undefined && (!Number.isInteger(durationMs / 1000) || durationMs <= 0))
      || !Number.isInteger(intervalSeconds) || intervalSeconds < 0) {
      ctx.logger.error('Invalid watch bounds: --cycles <n>=positive int, --duration <seconds>=positive int, --interval <seconds>=non-negative int.');
      return 1;
    }
    const run = await watchPipeline(root, { allowExec, cycles, durationMs, intervalSeconds });
    const files = await writeWatchReport(root, run);

    let evidence = null;
    if (args.record) {
      evidence = await recordEvidence(root, {
        type: 'ops-watch',
        status: run.verdict === 'PASS' ? 'PASS' : run.verdict === 'NOT_PROVEN' ? 'NOT_PROVEN' : 'FAIL',
        summary: `Ops watch ${run.runId}: ${run.verdict} (${run.cyclesRun ?? 0} cycle(s)${run.stoppedAt ? `, stopped at ${run.stoppedAt}` : ''}).`,
        createdBy: 'aidf',
        files: [files.json, files.markdown],
        checks: (run.cycles ?? []).flatMap((c) => c.steps.map((s) => ({ id: `${c.cycle}:${s.id}`, status: s.status, reason: s.reason }))),
        limitations: [
          'PASS covers the observed window only; it is not a guarantee of future health.',
          'Monitoring commands are project-owned configuration; review pipeline.yaml before enabling execution.',
        ],
      });
    }

    if (ctx.json) {
      process.stdout.write(`${JSON.stringify({ run, files, evidence }, null, 2)}\n`);
    } else {
      ctx.logger.info(`Ops watch ${run.runId}: ${run.verdict} (${run.cyclesRun ?? 0} cycle(s))`);
      for (const c of (run.cycles ?? []).slice(-3)) {
        for (const s of c.steps) {
          const line = `  cycle ${c.cycle} ${s.id}: ${s.status}`;
          if (s.status === 'PASS') ctx.logger.info(line);
          else ctx.logger.error(`${line} — ${s.reason}`);
        }
      }
      ctx.logger.info(`  report: ${files.markdown}`);
      if (evidence) ctx.logger.info(`  evidence: ${evidence.id}`);
      if (run.reason) ctx.logger.warn(`  ${run.reason}`);
      if (!allowExec) ctx.logger.warn('Execution was not permitted: pass --allow-exec or set security.allowCommandExecution=true.');
    }
    return run.verdict === 'PASS' ? 0 : 1;
  }

  if (sub === 'rollback') {
    const allowExec = opsEnabled(config, args);
    const run = await runRollback(root, { allowExec });
    const files = await writeOpsReport(root, run, 'rollback');

    let evidence = null;
    if (args.record) {
      evidence = await recordEvidence(root, {
        type: 'ops-rollback',
        status: run.verdict === 'PASS' ? 'PASS' : run.verdict === 'NOT_PROVEN' ? 'NOT_PROVEN' : 'FAIL',
        summary: `Ops rollback ${run.runId} for run ${run.rolledBackRun ?? 'none'}: ${run.verdict}.`,
        createdBy: 'aidf',
        files: [files.json, files.markdown],
        checks: run.steps.map((s) => ({ id: s.id, status: s.status, reason: s.reason })),
      });
    }

    if (ctx.json) {
      process.stdout.write(`${JSON.stringify({ run, files, evidence }, null, 2)}\n`);
    } else {
      ctx.logger.info(`Ops rollback ${run.runId}: ${run.verdict}`);
      for (const s of run.steps) {
        const line = `  ${s.id}: ${s.status}${s.exitCode != null ? ` (exit ${s.exitCode})` : ''}`;
        if (s.status === 'PASS') ctx.logger.info(line);
        else ctx.logger.error(`${line} — ${s.reason}`);
      }
      ctx.logger.info(`  report: ${files.markdown}`);
      if (evidence) ctx.logger.info(`  evidence: ${evidence.id}`);
      if (!allowExec) ctx.logger.warn('Execution was not permitted: pass --allow-exec or set security.allowCommandExecution=true.');
    }
    return run.verdict === 'PASS' ? 0 : 1;
  }

  ctx.logger.error(`Unknown ops subcommand: ${sub} (init|plan|run|rollback|watch|status)`);
  return 1;
}
