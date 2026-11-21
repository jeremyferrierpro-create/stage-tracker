#!/usr/bin/env node
/**
 * AIDF command dispatcher.
 *
 * Global flags are extracted before the command parses its own arguments so a
 * command can never silently ignore `--json`. Every command returns an exit
 * code: 0 success, 1 failure/blocked, 2 conflicts or contradictions.
 */
import { createLogger } from './util/logger.mjs';
import { FRAMEWORK_ROOT, AIDF_VERSION } from './core/paths.mjs';
import { ADAPTER_KEYS } from './core/adapters/index.mjs';

import { installCommand, initCommand, updateCommand } from './commands/install.mjs';
import { uninstallCommand } from './commands/uninstall.mjs';
import { setupCommand } from './commands/setup.mjs';
import { resourcesCommand } from './commands/resources.mjs';
import { doctorCommand } from './commands/doctor.mjs';
import { scanCommand } from './commands/scan.mjs';
import { skillsCommand } from './commands/skills.mjs';
import { workflowCommand } from './commands/workflow.mjs';
import { validateCommand } from './commands/validate.mjs';
import { evidenceCommand } from './commands/evidence.mjs';
import { docsCommand } from './commands/docs.mjs';
import { contextCommand } from './commands/context.mjs';
import { speckitCommand } from './commands/speckit.mjs';
import { configCommand } from './commands/config.mjs';
import { artefactsCommand } from './commands/artefacts.mjs';
import { requirementsCommand } from './commands/requirements.mjs';
import { traceabilityCommand } from './commands/traceability.mjs';
import { decisionCommand } from './commands/decision.mjs';
import { designCommand } from './commands/design.mjs';
import { securityCommand } from './commands/security.mjs';
import { testsCommand } from './commands/tests.mjs';
import { releaseCommand } from './commands/release.mjs';
import { opsCommand } from './commands/ops.mjs';

const COMMANDS = {
  init: { run: initCommand, summary: 'Install AIDF into a project (alias of install).' },
  install: { run: installCommand, summary: 'Install or refresh AIDF in a project.' },
  update: { run: updateCommand, summary: 'Re-apply the current AIDF version without losing project files.' },
  uninstall: { run: uninstallCommand, summary: 'Remove AIDF-managed files, preserving project-owned files.' },
  setup: { run: setupCommand, summary: 'Prepare optional resources (setup resources).' },
  resources: { run: resourcesCommand, summary: 'Inspect indexed external resources.' },
  doctor: { run: doctorCommand, summary: 'Verify the installation, adapter contract and evidence integrity.' },
  scan: { run: scanCommand, summary: 'Inventory the real project and environment.' },
  skills: { run: skillsCommand, summary: 'List, validate, show or link skills.' },
  workflow: { run: workflowCommand, summary: 'Show phases and move through gates.' },
  validate: { run: validateCommand, summary: 'Evaluate a phase gate and record evidence.' },
  evidence: { run: evidenceCommand, summary: 'Record, list and verify evidence.' },
  docs: { run: docsCommand, summary: 'List documents and change their status.' },
  context: { run: contextCommand, summary: 'Assemble the minimum context for a task.' },
  speckit: { run: speckitCommand, summary: 'Inspect, initialise or sync GitHub Spec Kit.' },
  config: { run: configCommand, summary: 'Read or change project configuration.' },
  artefacts: { run: artefactsCommand, summary: 'List, show, validate or verify structured artefacts.' },
  requirements: { run: requirementsCommand, summary: 'Manage the requirements registry and coverage.' },
  traceability: { run: traceabilityCommand, summary: 'Traceability report, chain verification and link checks.' },
  decision: { run: decisionCommand, summary: 'Evaluate a technology decision (deterministic engine).' },
  design: { run: designCommand, summary: 'Classify complexity and run the design workflow.' },
  security: { run: securityCommand, summary: 'Generate and inspect the STRIDE threat model artefact.' },
  tests: { run: testsCommand, summary: 'Generate a test plan artefact and report requirement coverage.' },
  release: { run: releaseCommand, summary: 'Build a release readiness report from gates, docs and evidence.' },
  ops: { run: opsCommand, summary: 'Run the project delivery pipeline (build/test/deploy) with evidence and rollback.' },
  version: { run: versionCommand, summary: 'Print the AIDF version.' },
  help: { run: helpCommand, summary: 'Show this help.' },
};

function versionCommand() {
  process.stdout.write(`${AIDF_VERSION}\n`);
  return 0;
}

function helpCommand(argv, ctx) {
  const lines = [];
  lines.push(`AIDF ${AIDF_VERSION} — AI Development Framework`);
  lines.push('');
  lines.push('Usage: aidf <command> [options]');
  lines.push('');
  lines.push('Commands:');
  const width = Math.max(...Object.keys(COMMANDS).map((k) => k.length));
  for (const [name, entry] of Object.entries(COMMANDS)) {
    lines.push(`  ${name.padEnd(width)}  ${entry.summary}`);
  }
  lines.push('');
  lines.push(`Adapters: ${ADAPTER_KEYS.join(', ')}`);
  lines.push('Global flags: --json, --version, --help');
  lines.push('');
  lines.push('Examples:');
  lines.push('  aidf install --adapter qoder --with-hooks');
  lines.push('  aidf context --task "add invoice export"');
  lines.push('  aidf validate --phase testing --kind exit --allow-exec');
  lines.push('  aidf workflow enter implementation');
  lines.push('');
  process.stdout.write(`${lines.join('\n')}\n`);
  return 0;
}

function extractGlobals(argv) {
  const globals = { json: false, quiet: false, debug: false, version: false };
  const rest = [];
  for (const token of argv) {
    if (token === '--json') globals.json = true;
    else if (token === '--quiet' || token === '-q') globals.quiet = true;
    else if (token === '--debug') globals.debug = true;
    else if (token === '--version' || token === '-v') globals.version = true;
    else rest.push(token);
  }
  return { globals, rest };
}

export async function main(argv) {
  const { globals, rest } = extractGlobals(argv);
  const logger = createLogger({
    json: globals.json,
    level: globals.quiet ? 'error' : (process.env.AIDF_LOG_LEVEL ?? 'info'),
  });
  const ctx = {
    logger,
    json: globals.json,
    frameworkRoot: FRAMEWORK_ROOT,
    cwd: process.cwd(),
  };

  if (globals.version) return versionCommand();
  if (globals.debug) process.env.AIDF_DEBUG = '1';

  const commandName = rest[0];
  if (!commandName || commandName === '--help' || commandName === '-h') {
    return helpCommand([], ctx);
  }
  const entry = COMMANDS[commandName];
  if (!entry) {
    logger.error(`Unknown command "${commandName}". Run "aidf help".`);
    return 1;
  }
  try {
    return await entry.run(rest.slice(1), ctx);
  } catch (error) {
    if (error && error.name === 'PathSafetyError') {
      logger.error(`Refused unsafe path: ${error.message}`);
      return 1;
    }
    logger.error(error && error.message ? error.message : String(error));
    if (process.env.AIDF_DEBUG === '1' && error?.stack) {
      process.stderr.write(`${error.stack}\n`);
    }
    return 1;
  }
}

export { COMMANDS };
