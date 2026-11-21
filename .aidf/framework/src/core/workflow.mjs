import path from 'node:path';
import { spawn } from 'node:child_process';
import { parseYaml } from '../util/yaml.mjs';
import { splitFrontmatter } from '../util/frontmatter.mjs';
import { readText, readJson, writeJson, exists, resolveInside } from '../util/fsx.mjs';
import { aidfPaths, FRAMEWORK_ROOT, PHASES } from './paths.mjs';
import { loadConfig } from './config.mjs';
import { loadEvidenceIndex, recordEvidence } from './evidence.mjs';

export const GATE_STATUSES = ['PASS', 'BLOCKED', 'FAIL', 'NOT_PROVEN', 'NOT_APPLICABLE'];

export async function loadPhases(frameworkRoot = FRAMEWORK_ROOT) {
  const file = path.join(frameworkRoot, 'assets', 'workflows', 'phases.yaml');
  const parsed = parseYaml(await readText(file));
  return parsed?.phases ?? [];
}

export async function loadGates(frameworkRoot = FRAMEWORK_ROOT) {
  const file = path.join(frameworkRoot, 'assets', 'workflows', 'gates.yaml');
  const parsed = parseYaml(await readText(file));
  return parsed?.gates ?? [];
}

export async function loadWorkflowState(root) {
  const file = path.join(aidfPaths(root).state, 'workflow.json');
  return (await readJson(file, null)) ?? {
    schemaVersion: 1,
    currentPhase: 'intake',
    completed: false,
    updatedAt: null,
    gates: {},
    history: [],
  };
}

export async function saveWorkflowState(root, state) {
  state.updatedAt = new Date().toISOString();
  await writeJson(path.join(aidfPaths(root).state, 'workflow.json'), state);
}

export function phaseById(phases, id) {
  return phases.find((p) => p.id === id) ?? null;
}

export function nextPhase(phases, id) {
  const index = phases.findIndex((p) => p.id === id);
  if (index === -1 || index === phases.length - 1) return null;
  return phases[index + 1].id;
}

export function tokenizeCommand(command) {
  const tokens = [];
  const re = /"([^"]*)"|'([^']*)'|(\S+)/g;
  let match;
  while ((match = re.exec(command)) !== null) {
    tokens.push(match[1] ?? match[2] ?? match[3]);
  }
  return tokens;
}

const SHELL_OPERATORS = /[|&;<>`]|\$\(/;

export async function runCommand(command, options = {}) {
  if (typeof command !== 'string' || command.trim() === '') {
    return { ok: false, status: 'BLOCKED', reason: 'Empty command.' };
  }
  if (SHELL_OPERATORS.test(command)) {
    return {
      ok: false,
      status: 'BLOCKED',
      reason: 'Shell operators are not allowed in AIDF command checks. Configure a single executable and arguments.',
    };
  }
  const tokens = tokenizeCommand(command);
  const executable = tokens.shift();
  const cwd = options.cwd ?? process.cwd();
  const timeoutMs = options.timeoutMs ?? 300000;
  return new Promise((resolve) => {
    const started = Date.now();
    let stdout = '';
    let stderr = '';
    let settled = false;
    const child = spawn(executable, tokens, {
      cwd,
      shell: false,
      windowsHide: true,
      env: { ...process.env },
    });
    const timer = setTimeout(() => {
      if (!settled) {
        child.kill('SIGKILL');
      }
    }, timeoutMs);
    child.stdout.on('data', (chunk) => {
      stdout += chunk.toString();
      if (stdout.length > 400000) stdout = stdout.slice(-400000);
    });
    child.stderr.on('data', (chunk) => {
      stderr += chunk.toString();
      if (stderr.length > 400000) stderr = stderr.slice(-400000);
    });
    child.on('error', (error) => {
      if (settled) return;
      settled = true;
      clearTimeout(timer);
      resolve({ ok: false, status: 'BLOCKED', reason: error.message, command, durationMs: Date.now() - started });
    });
    child.on('close', (code) => {
      if (settled) return;
      settled = true;
      clearTimeout(timer);
      resolve({
        ok: code === 0,
        status: code === 0 ? 'PASS' : 'FAIL',
        exitCode: code,
        command,
        cwd,
        durationMs: Date.now() - started,
        stdout,
        stderr,
      });
    });
  });
}

function statusFromChecks(checks, options = {}) {
  const blocking = checks.filter((c) => c.blocking !== false);
  const applicable = blocking.filter((c) => c.status !== 'NOT_APPLICABLE');

  // A gate with no blocking checks is informative by default. It only PASSes
  // when it declares that intent explicitly; otherwise it cannot silently pass.
  if (blocking.length === 0) {
    if (options.applicability !== 'informative') return 'NOT_PROVEN';
    if (checks.some((c) => c.status === 'FAIL')) return 'FAIL';
    if (checks.some((c) => c.status === 'BLOCKED')) return 'BLOCKED';
    return 'PASS';
  }

  // Blocking checks that are NOT_APPLICABLE cannot prove anything: a gate made
  // only of not-applicable blocking checks is NOT_PROVEN, never PASS.
  if (applicable.length === 0) return 'NOT_PROVEN';

  if (applicable.some((c) => c.status === 'FAIL')) return 'FAIL';
  if (applicable.some((c) => c.status === 'BLOCKED')) return 'BLOCKED';
  if (applicable.some((c) => c.status === 'NOT_PROVEN')) return 'NOT_PROVEN';
  return 'PASS';
}

async function checkFileExists(root, check) {
  try {
    const abs = resolveInside(root, check.path);
    if (await exists(abs)) return { status: 'PASS', reason: `${check.path} exists.` };
    return { status: 'NOT_PROVEN', reason: `${check.path} does not exist.` };
  } catch (error) {
    return { status: 'BLOCKED', reason: error.message };
  }
}

async function checkDocStatus(root, config, check) {
  const docs = check.documents ?? (check.document ? [check.document] : []);
  if (docs.length === 0) return { status: 'BLOCKED', reason: 'No document configured for doc-status check.' };
  const results = [];
  for (const doc of docs) {
    const entry = config.documents?.[doc];
    if (!entry) {
      results.push({ doc, status: 'BLOCKED', reason: `${doc} is not configured.` });
      continue;
    }

    // The file is canonical: a status recorded in config.json is only trusted
    // when the document frontmatter says the same. This closes the historical
    // hole where a config could claim VALIDATED while the file stayed a template.
    const file = path.join(root, '.aidf', 'docs', `${doc}.md`);
    if (!(await exists(file))) {
      results.push({ doc, status: 'BLOCKED', reason: `${doc} file is missing (.aidf/docs/${doc}.md).` });
      continue;
    }
    let fileStatus = null;
    try {
      const frontmatter = splitFrontmatter(await readText(file)).frontmatter;
      fileStatus = frontmatter && typeof frontmatter === 'object' ? frontmatter.status : null;
    } catch (error) {
      results.push({ doc, status: 'BLOCKED', reason: `${doc} file is unreadable: ${error.message}` });
      continue;
    }
    if (!fileStatus) {
      results.push({ doc, status: 'BLOCKED', reason: `${doc} file has no frontmatter status.` });
      continue;
    }
    if (String(fileStatus) !== String(entry.status)) {
      results.push({
        doc,
        status: 'FAIL',
        reason: `${doc} status mismatch: file=${fileStatus} config=${entry.status}.`,
      });
      continue;
    }

    const status = String(fileStatus);
    if (check.disallowed?.includes(status)) {
      results.push({ doc, status: 'FAIL', reason: `${doc} is ${status}.` });
    } else if (check.allowed && !check.allowed.includes(status)) {
      results.push({ doc, status: 'NOT_PROVEN', reason: `${doc} is ${status}; requires ${check.allowed.join(' or ')}.` });
    } else {
      results.push({ doc, status: 'PASS', reason: `${doc} is ${status}.` });
    }
  }
  const status = statusFromChecks(results.map((r) => ({ ...r, blocking: true })));
  return { status, reason: results.map((r) => `${r.doc}: ${r.reason}`).join(' '), details: results };
}

async function checkEvidence(root, check, config = {}) {
  const index = await loadEvidenceIndex(root);
  const requireAuth = config.security?.requireAuthenticatedApproval === true;
  const matches = index.entries.filter((e) => {
    if (check.evidenceType && e.type !== check.evidenceType) return false;
    if (check.gate && e.gate !== check.gate) return false;
    if (e.status !== 'PASS') return false;
    if (check.requireHumanApproval && !e.humanApproval) return false;
    if (check.requireHumanApproval && requireAuth && !e.authenticatedApproval) return false;
    if (check.maxAgeDays) {
      const age = (Date.now() - new Date(e.createdAt).getTime()) / 86400000;
      if (age > check.maxAgeDays) return false;
    }
    return true;
  });
  if (matches.length > 0) {
    const declaredOnly = matches.filter((e) => e.humanApproval && !e.authenticatedApproval).length;
    return {
      status: 'PASS',
      reason: `Found ${matches.length} matching evidence record(s).`
        + (declaredOnly > 0 ? ` ${declaredOnly} approval(s) are declared, not authenticated.` : ''),
      evidence: matches.slice(0, 5).map((m) => m.file),
      approval: check.requireHumanApproval
        ? (declaredOnly === 0 ? 'authenticated' : 'declared')
        : null,
    };
  }
  const anyType = index.entries.filter((e) => !check.evidenceType || e.type === check.evidenceType);
  if (check.requireHumanApproval && anyType.some((e) => e.status === 'PASS' && !e.humanApproval)) {
    return {
      status: 'BLOCKED',
      reason: `Evidence exists for "${check.evidenceType}" but lacks human approval.`,
    };
  }
  if (check.requireHumanApproval && requireAuth
    && anyType.some((e) => e.status === 'PASS' && e.humanApproval && !e.authenticatedApproval)) {
    return {
      status: 'NOT_PROVEN',
      reason: `Evidence for "${check.evidenceType}" has a declared approval only; authenticated approval is required.`,
    };
  }
  return {
    status: 'NOT_PROVEN',
    reason: `No PASS evidence of type "${check.evidenceType}"${check.requireHumanApproval ? ' with human approval' : ''}.`,
  };
}

async function checkCommand(root, config, check, options) {
  const key = check.commandKey;
  const command = config.commands?.[key];
  if (!command) {
    if (check.missing === 'NOT_APPLICABLE' && check.blocking === false) {
      return { status: 'NOT_APPLICABLE', reason: `No "${key}" command is configured (informative check).` };
    }
    return {
      status: 'BLOCKED',
      reason: `No "${key}" command is configured in .aidf/config.json.`
        + (check.missing === 'NOT_APPLICABLE' ? ' A blocking check cannot be NOT_APPLICABLE.' : ''),
    };
  }
  const allowedByConfig = config.security?.allowedCommands ?? [];
  if (allowedByConfig.length > 0 && !allowedByConfig.includes(key)) {
    return { status: 'BLOCKED', reason: `Command "${key}" is not in security.allowedCommands.` };
  }
  const permitted = options.allowExec || config.security?.allowCommandExecution === true;
  if (!permitted) {
    return {
      status: 'NOT_PROVEN',
      reason: `Command "${key}" was not executed. Re-run with --allow-exec or set security.allowCommandExecution=true.`,
      command,
    };
  }
  const result = await runCommand(command, { cwd: root, timeoutMs: check.timeoutMs ?? 300000 });
  return {
    status: result.status,
    reason: result.status === 'PASS' ? `Command exited 0 in ${result.durationMs}ms.` : result.reason ?? `Command exited ${result.exitCode}.`,
    command: {
      key,
      command,
      exitCode: result.exitCode ?? null,
      durationMs: result.durationMs ?? null,
      stdout: result.stdout ?? '',
      stderr: result.stderr ?? '',
    },
  };
}

function checkPreviousGate(state, check) {
  const gates = check.gates ?? (check.gate ? [check.gate] : []);
  if (gates.length === 0) return { status: 'BLOCKED', reason: 'No gate configured.' };
  const missing = gates.filter((g) => state.gates?.[g]?.status !== 'PASS');
  if (missing.length === 0) return { status: 'PASS', reason: `Prior gates PASS: ${gates.join(', ')}.` };
  return {
    status: 'NOT_PROVEN',
    reason: `Prior gates not PASS: ${missing.map((g) => `${g}=${state.gates?.[g]?.status ?? 'UNKNOWN'}`).join(', ')}.`,
  };
}

export async function evaluateGate(root, gate, options = {}) {
  const config = options.config ?? (await loadConfig(root));
  const state = options.state ?? (await loadWorkflowState(root));
  const checks = [];
  for (const check of gate.checks ?? []) {
    let result;
    switch (check.type) {
      case 'file-exists':
        result = await checkFileExists(root, check);
        break;
      case 'doc-status':
        result = await checkDocStatus(root, config, check);
        break;
      case 'evidence':
        result = await checkEvidence(root, check, config);
        break;
      case 'command':
        result = await checkCommand(root, config, check, options);
        break;
      case 'previous-gate':
        result = checkPreviousGate(state, check);
        break;
      case 'manual':
        result = { status: 'NOT_PROVEN', reason: check.description ?? 'Manual validation required.' };
        break;
      default:
        result = { status: 'BLOCKED', reason: `Unknown check type: ${check.type}` };
    }
    checks.push({
      id: check.id,
      type: check.type,
      blocking: check.blocking !== false,
      status: result.status,
      reason: result.reason,
      evidence: result.evidence ?? null,
      command: result.command ?? null,
      details: result.details ?? null,
    });
  }
  const status = statusFromChecks(checks, { applicability: gate.applicability });
  const blocking = checks.filter((c) => c.blocking !== false);
  const applicableBlocking = blocking.filter((c) => c.status !== 'NOT_APPLICABLE');
  let reason = null;
  if (blocking.length === 0 && gate.applicability !== 'informative') {
    reason = 'Gate has no blocking check and is not declared informative; it cannot silently PASS.';
  } else if (blocking.length > 0 && applicableBlocking.length === 0) {
    reason = 'No applicable blocking check: the gate cannot be proven from NOT_APPLICABLE checks alone.';
  }
  return {
    gate: gate.id,
    phase: gate.phase,
    kind: gate.kind,
    description: gate.description,
    status,
    reason,
    blockingChecks: blocking.length,
    applicableBlockingChecks: applicableBlocking.length,
    checks,
    evaluatedAt: new Date().toISOString(),
  };
}

export async function recordGateRun(root, report, options = {}) {
  const commands = report.checks
    .filter((c) => c.command)
    .map((c) => ({
      key: c.command.key,
      command: c.command.command,
      exitCode: c.command.exitCode,
      durationMs: c.command.durationMs,
      stdout: String(c.command.stdout ?? '').slice(-8000),
      stderr: String(c.command.stderr ?? '').slice(-8000),
    }));
  const evidence = await recordEvidence(root, {
    type: 'gate-run',
    status: report.status,
    gate: report.gate,
    phase: report.phase,
    summary: `${report.gate} (${report.kind}) -> ${report.status}`,
    createdBy: options.createdBy ?? 'aidf',
    checks: report.checks.map((c) => ({
      id: c.id,
      type: c.type,
      status: c.status,
      reason: c.reason,
      evidence: c.evidence,
    })),
    commands,
    limitations: options.limitations ?? [],
  });
  return evidence;
}

export async function runGate(root, gateId, options = {}) {
  const gates = options.gates ?? (await loadGates(options.frameworkRoot));
  const gate = gates.find((g) => g.id === gateId);
  if (!gate) throw new Error(`Unknown gate: ${gateId}`);
  const state = options.state ?? (await loadWorkflowState(root));
  const report = await evaluateGate(root, gate, { ...options, state });
  const evidence = options.record === false ? null : await recordGateRun(root, report, options);
  if (evidence) report.evidence = { id: evidence.id, file: evidence.file, hash: evidence.hash };
  state.gates[gateId] = {
    status: report.status,
    at: report.evaluatedAt,
    evidence: evidence?.file ?? state.gates[gateId]?.evidence ?? null,
  };
  await saveWorkflowState(root, state);
  report.state = state;
  return report;
}

export async function enterPhase(root, phaseId, options = {}) {
  const phases = await loadPhases(options.frameworkRoot);
  const phase = phaseById(phases, phaseId);
  if (!phase) throw new Error(`Unknown phase: ${phaseId}. Allowed: ${PHASES.join(', ')}`);
  const state = await loadWorkflowState(root);
  if (state.currentPhase === phaseId) {
    return { status: 'PASS', alreadyInPhase: true, phase: phaseId, state };
  }
  const gate = await runGate(root, `${phaseId}-entry`, { ...options, state });
  if (gate.status !== 'PASS') {
    return { status: gate.status, phase: phaseId, gate };
  }
  state.currentPhase = phaseId;
  state.history.push({ ts: new Date().toISOString(), event: 'enter', phase: phaseId, gate: gate.gate, status: gate.status });
  await saveWorkflowState(root, state);
  return { status: 'PASS', phase: phaseId, gate, state };
}

export async function exitPhase(root, phaseId, options = {}) {
  const phases = await loadPhases(options.frameworkRoot);
  const phase = phaseById(phases, phaseId);
  if (!phase) throw new Error(`Unknown phase: ${phaseId}`);
  const state = await loadWorkflowState(root);
  const gate = await runGate(root, `${phaseId}-exit`, { ...options, state });
  if (gate.status !== 'PASS') {
    return { status: gate.status, phase: phaseId, gate, state };
  }
  const next = nextPhase(phases, phaseId);
  state.history.push({ ts: new Date().toISOString(), event: 'exit', phase: phaseId, gate: gate.gate, status: gate.status });
  if (next) {
    state.currentPhase = next;
    state.completed = false;
  } else {
    state.completed = true;
  }
  await saveWorkflowState(root, state);
  return { status: 'PASS', phase: phaseId, nextPhase: next, gate, state };
}

export async function workflowStatus(root, options = {}) {
  const phases = await loadPhases(options.frameworkRoot);
  const state = await loadWorkflowState(root);
  const rows = phases.map((phase) => ({
    phase: phase.id,
    name: phase.name,
    entry: state.gates?.[`${phase.id}-entry`]?.status ?? 'UNKNOWN',
    exit: state.gates?.[`${phase.id}-exit`]?.status ?? 'UNKNOWN',
    current: state.currentPhase === phase.id ? 'yes' : '',
  }));
  return { currentPhase: state.currentPhase, completed: state.completed, phases: rows, state };
}
