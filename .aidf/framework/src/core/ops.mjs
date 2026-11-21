import path from 'node:path';
import { parseYaml } from '../util/yaml.mjs';
import { readText, readJson, writeJson, writeAtomic, ensureDir, exists, toPosix, timestampSlug } from '../util/fsx.mjs';
import { aidfPaths } from './paths.mjs';
import { runCommand } from './workflow.mjs';

export const OPS_STEP_KINDS = ['build', 'test', 'deploy', 'verify', 'custom'];

export const PIPELINE_TEMPLATE = `# AIDF operations pipeline — project-owned file.
# Steps run sequentially; the run stops on the first non-PASS step.
# Commands are executed without a shell: no pipes, redirects or operators.
# Execution requires --allow-exec or security.allowCommandExecution=true.
schemaVersion: 1
pipeline:
  steps:
    - id: build
      kind: build
      name: Build
      command: "npm run build"
      blocking: true
      timeoutMs: 300000
    - id: test
      kind: test
      name: Test
      command: "npm test"
      blocking: true
      timeoutMs: 300000
    - id: deploy
      kind: deploy
      name: Deploy
      command: ""
      blocking: true
      timeoutMs: 600000
  rollback:
    - id: rollback-deploy
      name: Rollback deploy
      command: ""
      timeoutMs: 300000
monitoring:
  # Optional continuous checks run by "aidf ops watch".
  # Same step schema as pipeline.steps; typically kind: verify.
  steps: []
`;

export async function loadPipeline(root) {
  const file = pipelineFilePath(root);
  if (!(await exists(file))) return { present: false, file, pipeline: null, errors: [] };
  let parsed;
  try {
    parsed = parseYaml(await readText(file));
  } catch (error) {
    return { present: true, file, pipeline: null, errors: [`pipeline.yaml is not valid YAML: ${error.message}`] };
  }
  const pipeline = parsed?.pipeline ?? null;
  if (pipeline && typeof pipeline === 'object' && parsed?.monitoring !== undefined) {
    pipeline.monitoring = parsed.monitoring;
  }
  const errors = validatePipeline(pipeline);
  return { present: true, file, pipeline, errors };
}

function pipelineFilePath(root) {
  return path.join(aidfPaths(root).workflows, 'pipeline.yaml');
}

export function validatePipeline(pipeline) {
  const errors = [];
  if (!pipeline || typeof pipeline !== 'object') {
    errors.push('Missing "pipeline" object.');
    return errors;
  }
  const steps = pipeline.steps;
  if (!Array.isArray(steps) || steps.length === 0) {
    errors.push('pipeline.steps must be a non-empty array.');
    return errors;
  }
  const ids = new Set();
  for (const [i, step] of steps.entries()) {
    const at = `steps[${i}]`;
    if (!step || typeof step !== 'object') {
      errors.push(`${at}: step must be an object.`);
      continue;
    }
    if (!step.id || typeof step.id !== 'string') errors.push(`${at}: missing string id.`);
    else if (ids.has(step.id)) errors.push(`${at}: duplicate id "${step.id}".`);
    else ids.add(step.id);
    if (step.kind !== undefined && !OPS_STEP_KINDS.includes(step.kind)) {
      errors.push(`${at} (${step.id ?? '?'}): unknown kind "${step.kind}" (allowed: ${OPS_STEP_KINDS.join(', ')}).`);
    }
    if (!step.command || typeof step.command !== 'string' || step.command.trim() === '') {
      errors.push(`${at} (${step.id ?? '?'}): missing command.`);
    }
  }
  if (pipeline.rollback !== undefined) {
    if (!Array.isArray(pipeline.rollback)) {
      errors.push('pipeline.rollback must be an array when present.');
    } else {
      for (const [i, step] of pipeline.rollback.entries()) {
        if (!step || typeof step.command !== 'string' || step.command.trim() === '') {
          errors.push(`rollback[${i}]: missing command.`);
        }
      }
    }
  }
  if (pipeline.monitoring !== undefined) {
    if (!pipeline.monitoring || typeof pipeline.monitoring !== 'object' || Array.isArray(pipeline.monitoring)) {
      errors.push('pipeline.monitoring must be an object.');
    } else if (pipeline.monitoring.steps !== undefined) {
      errors.push(...validateSteps(pipeline.monitoring.steps, 'monitoring.steps'));
    }
  }
  return errors;
}

export function validateSteps(steps, at) {
  const errors = [];
  if (!Array.isArray(steps)) {
    errors.push(`${at} must be an array.`);
    return errors;
  }
  const ids = new Set();
  for (const [i, step] of steps.entries()) {
    const where = `${at}[${i}]`;
    if (!step || typeof step !== 'object') {
      errors.push(`${where}: step must be an object.`);
      continue;
    }
    if (!step.id || typeof step.id !== 'string') errors.push(`${where}: missing string id.`);
    else if (ids.has(step.id)) errors.push(`${where}: duplicate id "${step.id}".`);
    else ids.add(step.id);
    if (step.kind !== undefined && !OPS_STEP_KINDS.includes(step.kind)) {
      errors.push(`${where} (${step.id ?? '?'}): unknown kind "${step.kind}".`);
    }
    if (!step.command || typeof step.command !== 'string' || step.command.trim() === '') {
      errors.push(`${where} (${step.id ?? '?'}): missing command.`);
    }
  }
  return errors;
}

export function monitoringSteps(pipeline) {
  return pipeline?.monitoring?.steps ?? [];
}

/**
 * Execute pipeline steps sequentially. Execution only happens when
 * options.allowExec is true; otherwise every step is reported NOT_PROVEN and
 * nothing runs. Stops on the first step that is not PASS.
 */
export async function runPipeline(root, options = {}) {
  const { pipeline, errors } = await loadPipeline(root);
  const startedAt = new Date().toISOString();
  const runId = `ops-${timestampSlug()}`;
  const allowExec = Boolean(options.allowExec);
  const onlyStep = options.onlyStep ?? null;

  if (!pipeline) {
    return {
      runId, startedAt, finishedAt: new Date().toISOString(),
      verdict: errors.length ? 'INVALID' : 'ABSENT',
      reason: errors[0] ?? 'No .aidf/workflows/pipeline.yaml found. Run "aidf ops init".',
      errors, steps: [],
    };
  }
  if (errors.length) {
    return { runId, startedAt, finishedAt: new Date().toISOString(), verdict: 'INVALID', errors, steps: [] };
  }

  let steps = pipeline.steps;
  if (onlyStep) {
    steps = steps.filter((s) => s.id === onlyStep);
    if (steps.length === 0) {
      return {
        runId, startedAt, finishedAt: new Date().toISOString(), verdict: 'INVALID',
        reason: `Unknown step "${onlyStep}".`, errors: [], steps: [],
      };
    }
  }

  const results = [];
  let stoppedAt = null;
  for (const step of steps) {
    const timeoutMs = step.timeoutMs ?? 300000;
    if (!allowExec) {
      results.push({
        id: step.id, kind: step.kind ?? 'custom', name: step.name ?? step.id,
        status: 'NOT_PROVEN',
        reason: 'Command execution not permitted. Pass --allow-exec or set security.allowCommandExecution=true.',
      });
      stoppedAt = step.id;
      break;
    }
    const outcome = await runCommand(step.command, { cwd: root, timeoutMs });
    const status = outcome.ok ? 'PASS' : outcome.status === 'BLOCKED' ? 'BLOCKED' : 'FAIL';
    results.push({
      id: step.id, kind: step.kind ?? 'custom', name: step.name ?? step.id,
      status,
      command: step.command,
      exitCode: outcome.exitCode ?? null,
      durationMs: outcome.durationMs ?? null,
      reason: outcome.reason ?? (outcome.ok ? `Exit code 0.` : `Exit code ${outcome.exitCode}.`),
      stdoutTail: (outcome.stdout ?? '').slice(-2000),
      stderrTail: (outcome.stderr ?? '').slice(-2000),
    });
    if (status !== 'PASS') {
      stoppedAt = step.id;
      break;
    }
  }

  const verdict = results.length === steps.length && results.every((r) => r.status === 'PASS')
    ? 'PASS'
    : results.some((r) => r.status === 'BLOCKED') ? 'BLOCKED'
    : results.some((r) => r.status === 'FAIL') ? 'FAIL'
    : 'NOT_PROVEN';

  return {
    runId,
    startedAt,
    finishedAt: new Date().toISOString(),
    verdict,
    stoppedAt,
    steps: results,
    deployStep: results.find((r) => r.status === 'PASS' && steps.find((s) => s.id === r.id)?.kind === 'deploy')?.id ?? null,
  };
}

/** Persist the last successful deploy so `aidf ops rollback` knows what to undo. */
export async function recordLastRun(root, run) {
  const { pipeline } = await loadPipeline(root);
  const rollbackSteps = pipeline?.rollback ?? [];
  const state = {
    schemaVersion: 1,
    runId: run.runId,
    finishedAt: run.finishedAt,
    verdict: run.verdict,
    deployStep: run.deployStep,
    rollback: rollbackSteps,
  };
  const file = path.join(aidfPaths(root).state, 'ops-last-run.json');
  await ensureDir(aidfPaths(root).state);
  await writeJson(file, state);
  return state;
}

export async function loadLastRun(root) {
  return readJson(path.join(aidfPaths(root).state, 'ops-last-run.json'), null);
}

/** Execute the rollback commands recorded for the last run. */
export async function runRollback(root, options = {}) {
  const last = await loadLastRun(root);
  const startedAt = new Date().toISOString();
  const runId = `ops-rb-${timestampSlug()}`;
  if (!last) {
    return { runId, startedAt, finishedAt: new Date().toISOString(), verdict: 'NOT_PROVEN', reason: 'No recorded ops run to roll back.', steps: [] };
  }
  if (!Array.isArray(last.rollback) || last.rollback.length === 0) {
    return { runId, startedAt, finishedAt: new Date().toISOString(), verdict: 'NOT_PROVEN', reason: `Run ${last.runId} recorded no rollback commands.`, steps: [] };
  }
  const allowExec = Boolean(options.allowExec);
  const results = [];
  for (const step of last.rollback) {
    if (!allowExec) {
      results.push({ id: step.id ?? 'rollback', status: 'NOT_PROVEN', reason: 'Command execution not permitted.' });
      break;
    }
    const outcome = await runCommand(step.command, { cwd: root, timeoutMs: step.timeoutMs ?? 300000 });
    const status = outcome.ok ? 'PASS' : outcome.status === 'BLOCKED' ? 'BLOCKED' : 'FAIL';
    results.push({
      id: step.id ?? 'rollback', status, command: step.command,
      exitCode: outcome.exitCode ?? null, durationMs: outcome.durationMs ?? null,
      reason: outcome.reason ?? (outcome.ok ? 'Exit code 0.' : `Exit code ${outcome.exitCode}.`),
      stdoutTail: (outcome.stdout ?? '').slice(-2000),
      stderrTail: (outcome.stderr ?? '').slice(-2000),
    });
    if (status !== 'PASS') break;
  }
  const verdict = results.every((r) => r.status === 'PASS') ? 'PASS'
    : results.some((r) => r.status === 'BLOCKED') ? 'BLOCKED'
    : results.some((r) => r.status === 'FAIL') ? 'FAIL' : 'NOT_PROVEN';
  return {
    runId, startedAt, finishedAt: new Date().toISOString(), verdict,
    rolledBackRun: last.runId, steps: results,
  };
}

/**
 * Run monitoring.steps repeatedly. Requires a bound: options.cycles or
 * options.durationMs, otherwise the watch would run forever.
 * Stops on the first non-PASS step result or when the bound is reached.
 */
export async function watchPipeline(root, options = {}) {
  const { pipeline, errors } = await loadPipeline(root);
  const startedAt = new Date().toISOString();
  const runId = `ops-watch-${timestampSlug()}`;
  const allowExec = Boolean(options.allowExec);
  const cycles = options.cycles ?? null;
  const durationMs = options.durationMs ?? null;

  if (!pipeline) {
    return {
      runId, startedAt, finishedAt: new Date().toISOString(), verdict: 'ABSENT',
      reason: errors[0] ?? 'No .aidf/workflows/pipeline.yaml found. Run "aidf ops init".', errors, cycles: [],
    };
  }
  if (errors.length) {
    return { runId, startedAt, finishedAt: new Date().toISOString(), verdict: 'INVALID', errors, cycles: [] };
  }
  const steps = monitoringSteps(pipeline);
  if (steps.length === 0) {
    return {
      runId, startedAt, finishedAt: new Date().toISOString(), verdict: 'NOT_PROVEN',
      reason: 'pipeline.monitoring.steps is empty. Configure health-check commands first.', cycles: [],
    };
  }
  if (!cycles && !durationMs) {
    return {
      runId, startedAt, finishedAt: new Date().toISOString(), verdict: 'NOT_PROVEN',
      reason: 'Watch requires a bound: pass --cycles <n> or --duration <seconds>.', cycles: [],
    };
  }

  const intervalMs = Math.max(0, (options.intervalSeconds ?? 60) * 1000);
  const deadline = durationMs ? Date.now() + durationMs : null;
  const maxCycles = cycles ?? Number.MAX_SAFE_INTEGER;
  const results = [];
  let cycle = 0;
  let stoppedAt = null;

  while (cycle < maxCycles && (!deadline || Date.now() < deadline)) {
    cycle += 1;
    const cycleResults = [];
    for (const step of steps) {
      if (!allowExec) {
        cycleResults.push({ id: step.id, status: 'NOT_PROVEN', reason: 'Execution not permitted.' });
        break;
      }
      const outcome = await runCommand(step.command, { cwd: root, timeoutMs: step.timeoutMs ?? 300000 });
      const status = outcome.ok ? 'PASS' : outcome.status === 'BLOCKED' ? 'BLOCKED' : 'FAIL';
      cycleResults.push({
        id: step.id, kind: step.kind ?? 'verify', status, command: step.command,
        exitCode: outcome.exitCode ?? null, durationMs: outcome.durationMs ?? null,
        reason: outcome.reason ?? (outcome.ok ? 'Exit code 0.' : `Exit code ${outcome.exitCode}.`),
      });
      if (status !== 'PASS') break;
    }
    results.push({ cycle, at: new Date().toISOString(), steps: cycleResults });
    const failed = cycleResults.find((s) => s.status !== 'PASS');
    if (failed) {
      stoppedAt = `${failed.id} (cycle ${cycle})`;
      break;
    }
    if (cycle < maxCycles && (!deadline || Date.now() + intervalMs < deadline)) {
      await new Promise((r) => setTimeout(r, intervalMs));
    }
  }

  const allPass = results.length > 0 && results.every((c) => c.steps.every((s) => s.status === 'PASS'));
  const verdict = allPass ? 'PASS'
    : results.some((c) => c.steps.some((s) => s.status === 'BLOCKED')) ? 'BLOCKED'
    : results.some((c) => c.steps.some((s) => s.status === 'FAIL')) ? 'FAIL'
    : 'NOT_PROVEN';

  return {
    runId, startedAt, finishedAt: new Date().toISOString(), verdict, stoppedAt,
    cyclesRun: results.length, cycles: results,
  };
}

export function renderWatchMarkdown(run) {
  const lines = [];
  lines.push('# AIDF Ops Watch Report');
  lines.push('');
  lines.push(`- Run: ${run.runId}`);
  lines.push(`- Started: ${run.startedAt}`);
  lines.push(`- Finished: ${run.finishedAt}`);
  lines.push(`- Cycles run: ${run.cyclesRun ?? 0}`);
  lines.push(`- Verdict: **${run.verdict}**`);
  if (run.stoppedAt) lines.push(`- Stopped at: ${run.stoppedAt}`);
  if (run.reason) lines.push(`- Reason: ${run.reason}`);
  lines.push('');
  for (const c of run.cycles ?? []) {
    lines.push(`## Cycle ${c.cycle} — ${c.at}`);
    lines.push('');
    lines.push('| Step | Status | Exit | Reason |');
    lines.push('|---|---|---|---|');
    for (const s of c.steps) lines.push(`| ${s.id} | ${s.status} | ${s.exitCode ?? '-'} | ${s.reason ?? ''} |`);
    lines.push('');
  }
  lines.push('## Limitations');
  lines.push('');
  lines.push('- A PASS verdict means every monitoring command exited 0 during the observed window; it is not a guarantee of future health.');
  lines.push('- Monitoring commands are project-owned configuration; review pipeline.yaml before enabling execution.');
  lines.push('');
  return `${lines.join('\n')}\n`;
}

export async function writeWatchReport(root, run) {
  const reportsDir = aidfPaths(root).reports;
  await ensureDir(reportsDir);
  const stamp = `${run.startedAt.slice(0, 10)}-${run.runId}`;
  const jsonPath = path.join(reportsDir, `OPS-WATCH-${stamp}.json`);
  const mdPath = path.join(reportsDir, `OPS-WATCH-${stamp}.md`);
  await writeJson(jsonPath, run);
  await writeAtomic(mdPath, renderWatchMarkdown(run));
  return { json: toPosix(jsonPath), markdown: toPosix(mdPath) };
}

export function renderOpsMarkdown(run, kind = 'run') {
  const lines = [];
  lines.push(`# AIDF Ops ${kind === 'rollback' ? 'Rollback' : 'Pipeline'} Report`);
  lines.push('');
  lines.push(`- Run: ${run.runId}`);
  lines.push(`- Started: ${run.startedAt}`);
  lines.push(`- Finished: ${run.finishedAt}`);
  lines.push(`- Verdict: **${run.verdict}**`);
  if (run.stoppedAt) lines.push(`- Stopped at step: ${run.stoppedAt}`);
  if (run.rolledBackRun) lines.push(`- Rolled back run: ${run.rolledBackRun}`);
  if (run.reason) lines.push(`- Reason: ${run.reason}`);
  lines.push('');
  lines.push('| Step | Kind | Status | Exit | Duration | Reason |');
  lines.push('|---|---|---|---|---|---|');
  for (const s of run.steps) {
    lines.push(`| ${s.id} | ${s.kind ?? '-'} | ${s.status} | ${s.exitCode ?? '-'} | ${s.durationMs != null ? `${s.durationMs}ms` : '-'} | ${s.reason ?? ''} |`);
  }
  lines.push('');
  lines.push('## Limitations');
  lines.push('');
  lines.push('- Commands run without a shell: pipes, redirects and operators are rejected.');
  lines.push('- pipeline.yaml is a project-owned file; review it before enabling execution.');
  lines.push('- A PASS verdict means the configured commands exited 0; it is not a deployment health guarantee.');
  lines.push('');
  return `${lines.join('\n')}\n`;
}

export async function writeOpsReport(root, run, kind = 'run') {
  const reportsDir = aidfPaths(root).reports;
  await ensureDir(reportsDir);
  const stamp = `${run.startedAt.slice(0, 10)}-${run.runId}`;
  const jsonPath = path.join(reportsDir, `OPS-${kind === 'rollback' ? 'RB-' : ''}${stamp}.json`);
  const mdPath = path.join(reportsDir, `OPS-${kind === 'rollback' ? 'RB-' : ''}${stamp}.md`);
  await writeJson(jsonPath, run);
  await writeAtomic(mdPath, renderOpsMarkdown(run, kind));
  return { json: toPosix(jsonPath), markdown: toPosix(mdPath) };
}
