import path from 'node:path';
import { parseArgs } from '../util/args.mjs';
import { resolveProjectRoot, isInitialized, aidfPaths } from '../core/paths.mjs';
import { attachLogFile } from '../util/logger.mjs';
import { writeJson, writeAtomic, ensureDir, exists, toPosix } from '../util/fsx.mjs';
import { loadGates, loadWorkflowState, evaluateGate } from '../core/workflow.mjs';
import { loadConfig, defaultConfig } from '../core/config.mjs';
import { loadEvidenceIndex } from '../core/evidence.mjs';
import { detectProject } from '../core/detect.mjs';
import { collectArtefactFiles } from '../core/traceability/chain.mjs';
import { recordEvidence } from '../core/evidence.mjs';

export function releaseArgSpec() {
  return {
    booleans: ['json', 'help', 'record', 'strict', 'allow-exec'],
    aliases: { h: 'help', t: 'target' },
  };
}

/**
 * Build a release readiness report. Read-only unless --record is passed:
 * gates are evaluated without recording gate-run evidence, and no workflow
 * state is mutated.
 */
export async function buildReleaseReport(root, options = {}) {
  const now = new Date().toISOString();
  const gates = await loadGates(options.frameworkRoot);
  const state = await loadWorkflowState(root);
  const config = await loadConfig(root).catch(() => defaultConfig({}));

  const gateResults = [];
  for (const gate of gates) {
    const report = await evaluateGate(root, gate, {
      ...options,
      config,
      state,
      allowExec: Boolean(options['allow-exec'] ?? options.allowExec),
    });
    gateResults.push({
      id: report.gate,
      phase: report.phase,
      kind: report.kind,
      status: report.status,
      reason: report.reason,
      checks: report.checks.map((c) => ({ id: c.id, type: c.type, status: c.status, reason: c.reason })),
    });
  }

  const blockers = gateResults.filter(
    (g) => (g.status === 'BLOCKED' || g.status === 'FAIL')
      && g.checks.some((c) => c.status === 'BLOCKED' || c.status === 'FAIL'),
  );
  const notProven = gateResults.filter((g) => g.status === 'NOT_PROVEN');
  const passed = gateResults.filter((g) => g.status === 'PASS');
  const ready = blockers.length === 0 && notProven.length === 0;

  const evidenceIndex = await loadEvidenceIndex(root);
  const evidenceByStatus = {};
  for (const e of evidenceIndex.entries ?? []) {
    evidenceByStatus[e.status] = (evidenceByStatus[e.status] ?? 0) + 1;
  }

  const project = await detectProject(root);
  const artefacts = await collectArtefactFiles(root);

  const docs = Object.entries(config.documents ?? {}).map(([doc, entry]) => ({
    doc,
    status: entry.status,
    validatedBy: entry.validatedBy ?? null,
    validatedAt: entry.validatedAt ?? null,
  }));

  const changelogPath = path.join(root, '.aidf', 'docs', 'CHANGELOG.md');
  const changelog = await exists(changelogPath);

  return {
    schemaVersion: 1,
    generatedAt: now,
    root,
    verdict: ready ? 'READY' : 'NOT_READY',
    summary: {
      gates: gateResults.length,
      pass: passed.length,
      blocked: blockers.length,
      notProven: notProven.length,
      notApplicable: gateResults.filter((g) => g.status === 'NOT_APPLICABLE').length,
    },
    blockers: blockers.map((g) => ({ id: g.id, phase: g.phase, kind: g.kind, reason: g.reason })),
    notProvenGates: notProven.map((g) => ({ id: g.id, phase: g.phase, kind: g.kind, reason: g.reason })),
    gates: gateResults,
    documents: docs,
    changelog: { present: changelog, path: toPosix(path.join('.aidf', 'docs', 'CHANGELOG.md')) },
    evidence: {
      total: evidenceIndex.entries?.length ?? 0,
      byStatus: evidenceByStatus,
    },
    artefacts: {
      total: artefacts.length,
      byType: artefacts.reduce((acc, f) => {
        const t = f.body?.type ?? 'unknown';
        acc[t] = (acc[t] ?? 0) + 1;
        return acc;
      }, {}),
    },
    project: {
      git: project.git,
      tests: { runners: project.tests.runners, directories: project.tests.directories },
      frameworks: project.frameworks,
    },
    limitations: [
      'Gate evaluation is read-only: no gate-run evidence is recorded and workflow state is not mutated.',
      'Command checks report NOT_PROVEN unless --allow-exec (or security.allowCommandExecution) permits execution.',
      'READY means every gate evaluated PASS or NOT_APPLICABLE; it is not a deployment authorization.',
    ],
  };
}

function renderReleaseMarkdown(report) {
  const lines = [];
  lines.push(`# AIDF Release Report`);
  lines.push('');
  lines.push(`- Generated: ${report.generatedAt}`);
  lines.push(`- Verdict: **${report.verdict}**`);
  lines.push(`- Gates: ${report.summary.pass} PASS / ${report.summary.blocked} BLOCKED / ${report.summary.notProven} NOT_PROVEN / ${report.summary.notApplicable} NOT_APPLICABLE`);
  lines.push('');
  lines.push('## Blockers');
  lines.push('');
  if (report.blockers.length === 0) lines.push('_None._');
  for (const b of report.blockers) lines.push(`- ${b.id} (${b.phase}/${b.kind}): ${b.reason}`);
  lines.push('');
  lines.push('## Gates');
  lines.push('');
  lines.push('| Gate | Phase | Kind | Status | Reason |');
  lines.push('|---|---|---|---|---|');
  for (const g of report.gates) lines.push(`| ${g.id} | ${g.phase} | ${g.kind} | ${g.status} | ${g.reason} |`);
  lines.push('');
  lines.push('## Documents');
  lines.push('');
  lines.push('| Document | Status | Validated by |');
  lines.push('|---|---|---|');
  for (const d of report.documents) lines.push(`| ${d.doc} | ${d.status} | ${d.validatedBy ?? '-'} |`);
  lines.push('');
  lines.push(`CHANGELOG.md: ${report.changelog.present ? 'present' : 'MISSING'}`);
  lines.push('');
  lines.push(`Evidence records: ${report.evidence.total} (${Object.entries(report.evidence.byStatus).map(([k, v]) => `${k}:${v}`).join(', ') || 'none'})`);
  lines.push(`Artefacts: ${report.artefacts.total}`);
  lines.push('');
  lines.push('## Limitations');
  lines.push('');
  for (const l of report.limitations) lines.push(`- ${l}`);
  lines.push('');
  return `${lines.join('\n')}\n`;
}

export async function releaseCommand(argv, ctx) {
  const args = parseArgs(argv, releaseArgSpec());
  if (args.help || args._[0] === 'help') {
    ctx.logger.info('Usage: aidf release report [--allow-exec] [--record] [--strict] [--json]');
    return 0;
  }
  const root = resolveProjectRoot({ target: args.target, cwd: process.cwd() });
  if (!isInitialized(root)) {
    ctx.logger.error(`AIDF is not initialized in ${root}. Run "aidf install" first.`);
    return 1;
  }
  await attachLogFile(ctx.logger, root).catch(() => null);

  const sub = args._[0] ?? 'report';
  if (sub !== 'report') {
    ctx.logger.error(`Unknown release subcommand: ${sub}`);
    return 1;
  }

  const report = await buildReleaseReport(root, {
    frameworkRoot: ctx.frameworkRoot,
    'allow-exec': args['allow-exec'],
    allowExec: args['allow-exec'],
  });

  const reportsDir = aidfPaths(root).reports;
  await ensureDir(reportsDir);
  const stamp = report.generatedAt.slice(0, 10);
  const jsonPath = path.join(reportsDir, `RELEASE-${stamp}.json`);
  const mdPath = path.join(reportsDir, `RELEASE-${stamp}.md`);
  await writeJson(jsonPath, report);
  await writeAtomic(mdPath, renderReleaseMarkdown(report));

  let evidence = null;
  if (args.record) {
    evidence = await recordEvidence(root, {
      type: 'release-report',
      status: report.verdict === 'READY' ? 'PASS' : 'NOT_PROVEN',
      summary: `Release report ${stamp}: ${report.verdict} (${report.summary.pass} PASS gates, ${report.summary.blocked} blocked, ${report.summary.notProven} not proven).`,
      createdBy: 'aidf',
      files: [toPosix(jsonPath), toPosix(mdPath)],
      checks: report.gates.map((g) => ({ id: g.id, status: g.status, reason: g.reason })),
      limitations: report.limitations,
    });
  }

  if (ctx.json) {
    process.stdout.write(`${JSON.stringify({ report, files: { json: toPosix(jsonPath), markdown: toPosix(mdPath) }, evidence }, null, 2)}\n`);
  } else {
    ctx.logger.info(`Release report: ${report.verdict}`);
    ctx.logger.info(`  gates ${report.summary.pass}/${report.summary.gates} PASS, ${report.summary.blocked} blocked, ${report.summary.notProven} NOT_PROVEN`);
    for (const b of report.blockers.slice(0, 10)) ctx.logger.error(`  blocker ${b.id}: ${b.reason}`);
    ctx.logger.info(`  written: ${toPosix(mdPath)}`);
    if (evidence) ctx.logger.info(`  evidence: ${evidence.id}`);
    if (report.verdict !== 'READY') ctx.logger.warn('NOT_READY is not a failure of the report itself; resolve blockers or NOT_PROVEN gates.');
  }

  if (args.strict && report.verdict !== 'READY') return 1;
  return 0;
}
