import path from 'node:path';
import { parseArgs } from '../util/args.mjs';
import { resolveProjectRoot, isInitialized, aidfPaths } from '../core/paths.mjs';
import { loadConfig, validateConfig } from '../core/config.mjs';
import { loadManifest } from '../core/manifest.mjs';
import { buildSkillRegistry } from '../core/skills.mjs';
import { specKitStatus } from '../core/speckit.mjs';
import { loadEvidenceIndex, verifyEvidenceChain } from '../core/evidence.mjs';
import { loadWorkflowState } from '../core/workflow.mjs';
import { getAdapter } from '../core/adapters/index.mjs';
import { detectEnvironment } from '../core/detect.mjs';
import { exists, readText } from '../util/fsx.mjs';
import { attachLogFile } from '../util/logger.mjs';

export function doctorArgSpec() {
  return {
    booleans: ['json', 'help'],
    aliases: { h: 'help', t: 'target' },
  };
}

export async function doctorCommand(argv, ctx) {
  const args = parseArgs(argv, doctorArgSpec());
  if (args.help) {
    ctx.logger.info('Usage: aidf doctor [--target <dir>] [--json]');
    return 0;
  }
  const root = resolveProjectRoot({ target: args.target, cwd: process.cwd() });
  if (!isInitialized(root)) {
    ctx.logger.error(`AIDF is not initialized in ${root}. Run "aidf install" first.`);
    return 1;
  }
  await attachLogFile(ctx.logger, root).catch(() => null);
  const env = await detectEnvironment();
  const checks = [];
  const nodeMajor = Number(process.versions.node.split('.')[0]);
  checks.push({
    id: 'node-version',
    status: nodeMajor >= 20 ? 'PASS' : 'FAIL',
    detail: `Node ${process.versions.node}`,
  });

  let config = null;
  let configError = null;
  try {
    config = await loadConfig(root);
    const errors = validateConfig(config);
    if (errors.length > 0) configError = errors.join('; ');
  } catch (error) {
    configError = error.message;
  }
  checks.push({
    id: 'config',
    status: configError ? 'FAIL' : 'PASS',
    detail: configError ?? `.aidf/config.json (adapter=${config.adapter})`,
  });

  let manifest = null;
  try {
    manifest = await loadManifest(root);
    const missing = [];
    for (const rel of Object.keys(manifest.files)) {
      if (!(await exists(path.join(root, rel)))) missing.push(rel);
    }
    checks.push({
      id: 'manifest',
      status: missing.length === 0 ? 'PASS' : 'FAIL',
      detail: missing.length === 0
        ? `${Object.keys(manifest.files).length} managed files present`
        : `${missing.length} managed file(s) missing: ${missing.slice(0, 5).join(', ')}`,
    });
  } catch (error) {
    checks.push({ id: 'manifest', status: 'FAIL', detail: error.message });
  }

  if (config) {
    const docs = Object.entries(config.documents);
    const counts = {};
    for (const [, entry] of docs) counts[entry.status] = (counts[entry.status] ?? 0) + 1;
    // Cross-check config statuses against the real document frontmatter:
    // a config saying VALIDATED while the file says DRAFT (template without
    // content) is a genuine FAIL, not a PASS.
    const { splitFrontmatter } = await import('../util/frontmatter.mjs');
    const mismatches = [];
    for (const [doc, entry] of docs) {
      const file = path.join(root, '.aidf', 'docs', `${doc}.md`);
      if (!(await exists(file))) continue;
      try {
        const fm = splitFrontmatter(await readText(file)).frontmatter;
        const fileStatus = fm && typeof fm === 'object' ? fm.status : null;
        if (fileStatus && fileStatus !== entry.status) {
          mismatches.push(`${doc}: file=${fileStatus} config=${entry.status}`);
        }
      } catch {
        mismatches.push(`${doc}: unreadable document file`);
      }
    }
    checks.push({
      id: 'documents',
      status: mismatches.length > 0 ? 'FAIL' : counts.VALIDATED > 0 ? 'PASS' : 'WARN',
      detail:
        (Object.entries(counts).map(([k, v]) => `${k}=${v}`).join(' '))
        + (mismatches.length > 0 ? ` | status mismatch: ${mismatches.slice(0, 5).join('; ')}` : ''),
      problems: mismatches,
    });
    const missingDocs = [];
    for (const doc of Object.keys(config.documents)) {
      if (!(await exists(path.join(root, '.aidf', 'docs', `${doc}.md`)))) missingDocs.push(doc);
    }
    checks.push({
      id: 'document-files',
      status: missingDocs.length === 0 ? 'PASS' : 'FAIL',
      detail: missingDocs.length === 0 ? 'all document files present' : `missing: ${missingDocs.join(', ')}`,
    });

    const adapter = getAdapter(config.adapter);
    const verification = await adapter.verify({
      root,
      frameworkRoot: ctx.frameworkRoot,
      config,
      env,
      withHooks: Boolean(manifest?.files?.['.aidf/hooks/audit.mjs']),
    });
    checks.push({
      id: `adapter-${config.adapter}`,
      status: verification.status === 'FAIL' ? 'FAIL' : verification.status === 'NOT_PROVEN' ? 'NOT_PROVEN' : 'PASS',
      detail: `${verification.status}: ${verification.checks.filter((c) => c.status === 'PASS').length}/${verification.checks.length} checks PASS`,
      checks: verification.checks,
      limitations: verification.limitations,
    });
  }

  const registry = await buildSkillRegistry(root, { adapter: config?.adapter ?? 'qoder', frameworkRoot: ctx.frameworkRoot });
  const builtinBad = registry.skills.filter((s) => s.source === 'builtin' && s.status !== 'AVAILABLE');
  checks.push({
    id: 'skills',
    status: builtinBad.length === 0 ? 'PASS' : 'FAIL',
    detail: `${registry.summary.AVAILABLE ?? 0} available, ${registry.summary.BLOCKED ?? 0} blocked, ${registry.summary.INCOMPATIBLE ?? 0} incompatible`,
    problems: builtinBad.map((s) => `${s.id}: ${s.status}`),
  });

  const speckit = await specKitStatus(root, { frameworkRoot: ctx.frameworkRoot, specifyPath: env.specKit.cliPath });
  checks.push({
    id: 'spec-kit',
    status: speckit.present ? (speckit.integrationConfigured ? 'PASS' : 'WARN') : 'NOT_APPLICABLE',
    detail: speckit.present
      ? `.specify present; integration skills=${speckit.integrationSkills.length}; constitution=${speckit.constitutionSync}`
      : '.specify absent',
  });

  const evidenceIndex = await loadEvidenceIndex(root);
  const chain = await verifyEvidenceChain(root);
  checks.push({
    id: 'evidence-integrity',
    status: chain.ok ? 'PASS' : 'FAIL',
    detail: chain.ok
      ? `${chain.checked} evidence record(s) verified (hash + previous links + index)`
      : `${chain.problems.length} chain problem(s): ${chain.problems.slice(0, 5).map((p) => `${p.code}${p.file ? ` (${p.file})` : ''}`).join('; ')}`,
    problems: chain.problems.map((p) => `${p.code}: ${p.file ?? 'index'} — ${p.reason}`),
  });

  const declaredOnly = evidenceIndex.entries.filter((e) => e.humanApproval && !e.authenticatedApproval);
  checks.push({
    id: 'approvals',
    status: declaredOnly.length > 0 ? 'WARN' : 'PASS',
    detail: declaredOnly.length > 0
      ? `${declaredOnly.length} declared approval(s) are not authenticated (requireAuthenticatedApproval=${config?.security?.requireAuthenticatedApproval === true}).`
      : 'no declared-only approval recorded',
  });

  const state = await loadWorkflowState(root);
  checks.push({
    id: 'workflow',
    status: 'PASS',
    detail: `phase=${state.currentPhase}${state.completed ? ' (completed)' : ''}`,
  });

  if (config) {
    checks.push({
      id: 'security-policy',
      status: 'PASS',
      detail: `commandExecution=${config.security.allowCommandExecution} network=${config.security.allowNetwork} approvals=${(config.security.requireHumanApprovalFor ?? []).join(',')} authenticatedApprovalRequired=${config.security.requireAuthenticatedApproval === true}`,
    });
  }

  if (ctx.json) {
    process.stdout.write(`${JSON.stringify({ root, checks, skills: registry.summary, specKit: speckit }, null, 2)}\n`);
  } else {
    ctx.logger.info(`AIDF doctor — ${root}`);
    ctx.logger.table(
      checks.map((c) => ({ id: c.id, status: c.status, detail: c.detail })),
      [
        { key: 'id', label: 'Check' },
        { key: 'status', label: 'Status' },
        { key: 'detail', label: 'Detail' },
      ],
    );
    const failed = checks.filter((c) => c.status === 'FAIL');
    const notProven = checks.filter((c) => c.status === 'NOT_PROVEN');
    if (failed.length > 0) ctx.logger.error(`${failed.length} check(s) failed.`);
    if (notProven.length > 0) ctx.logger.warn(`${notProven.length} check(s) NOT_PROVEN; see adapter checks.`);
    const adapterCheck = checks.find((c) => c.id.startsWith('adapter-'));
    if (adapterCheck?.checks) {
      for (const c of adapterCheck.checks.filter((x) => x.status === 'NOT_PROVEN')) {
        ctx.logger.warn(`${c.id}: ${c.limitation ?? 'not proven'}`);
      }
    }
  }
  return checks.some((c) => c.status === 'FAIL') ? 1 : 0;
}
