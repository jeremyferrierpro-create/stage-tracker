import path from 'node:path';
import { aidfPaths } from './paths.mjs';
import { writeJson, readJson, ensureDir } from '../util/fsx.mjs';
import { detectProject } from './detect.mjs';
import { loadConfig, defaultConfig } from './config.mjs';
import { loadRequirementsRegistry } from './requirements/registry.mjs';
import { collectArtefactFiles } from './traceability/chain.mjs';

function nextTestPlanId(existingIds) {
  let max = 0;
  for (const id of existingIds) {
    const m = /^TP-(\d+)$/.exec(id);
    if (m) max = Math.max(max, parseInt(m[1], 10));
  }
  return `TP-${String(max + 1).padStart(3, '0')}`;
}

function guessTestType(text) {
  const t = String(text ?? '').toLowerCase();
  if (/e2e|end[- ]to[- ]end|browser/.test(t)) return 'e2e';
  if (/api|endpoint|http|rest/.test(t)) return 'api';
  if (/security|auth|permission|secret/.test(t)) return 'security';
  if (/accessib|a11y|wcag/.test(t)) return 'accessibility';
  if (/perf|latency|throughput|load/.test(t)) return 'performance';
  return 'unit';
}

function slugify(text) {
  return String(text ?? 'case')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
    .slice(0, 48) || 'case';
}

/**
 * Generate a test plan artefact (TP-###) from the requirements registry and
 * detected test runners. Test cases are planned, never claimed passing.
 */
export async function generateTestPlan(root, options = {}) {
  const now = new Date().toISOString();
  const project = await detectProject(root);
  const config = await loadConfig(root).catch(() => defaultConfig({}));
  const registry = await loadRequirementsRegistry(root);
  const files = await collectArtefactFiles(root);

  const requirements = (registry.requirements ?? []).filter((r) => r.status !== 'BLOCKED');
  const testDir = project.tests.directories[0] ?? 'tests';
  const testCommand = config.commands?.test ?? null;

  const testCases = [];
  for (const req of requirements) {
    const criteria = req.acceptanceCriteria?.length
      ? req.acceptanceCriteria
      : [{ id: `${req.id}-AC1`, description: req.title ?? req.id }];
    criteria.forEach((criterion, index) => {
      const critText = typeof criterion === 'string' ? criterion : (criterion.description ?? criterion.text ?? criterion.id);
      const id = `TC-${req.id}-${index + 1}`;
      testCases.push({
        id,
        name: `${req.id}: ${String(critText).slice(0, 80)}`,
        type: options.type ?? guessTestType(critText),
        path: path.posix.join(testDir, `${slugify(req.id)}-${index + 1}.test.mjs`),
        command: testCommand ?? 'NOT_CONFIGURED',
        status: 'planned',
        requirement: req.id,
        criterion: typeof criterion === 'object' ? criterion.id : null,
      });
    });
  }

  const runners = project.tests.runners.filter((r) => r !== 'npm:test');
  const artefact = {
    id: nextTestPlanId(files.map((f) => f.id)),
    schemaVersion: '1.0.0',
    type: 'test-plan',
    createdAt: now,
    updatedAt: now,
    status: 'DRAFT',
    strategy: {
      approach: 'One planned test case per acceptance criterion; types inferred from criterion wording and confirmed by a human.',
      coverageGoals: {},
      tools: [
        ...runners.map((r) => ({ name: r, source: 'detected' })),
        { name: 'aidf evidence', source: 'framework', note: 'results recorded via aidf evidence record' },
      ],
      mockPolicy: 'Mocks are allowed for external services in unit tests; integration and e2e cases must exercise real boundaries or be marked NOT_PROVEN.',
    },
    testCases,
    source: {
      type: 'generated',
      author: 'aidf',
      createdAt: now,
      generator: 'test-plan@0.3.2',
    },
    links: {
      covers: requirements.map((r) => r.id),
    },
    validation: {
      schemaVersion: '1.0.0',
      timestamp: now,
      valid: true,
      errors: [],
      warnings: [
        'Test cases are planned, not executed. Update status only with real test output evidence.',
        ...(testCommand ? [] : ['No test command configured (.aidf/config.json commands.test); set it to enable execution.']),
      ],
    },
    detection: {
      runners: project.tests.runners,
      testDirectories: project.tests.directories,
      packageManager: project.tests.packageManager,
    },
  };

  return artefact;
}

export async function saveTestPlan(root, artefact) {
  const file = aidfPaths(root).artefacts.testPlan;
  await ensureDir(path.dirname(file));
  await writeJson(file, artefact);
  return file;
}

export async function loadTestPlan(root) {
  const file = aidfPaths(root).artefacts.testPlan;
  return readJson(file, null);
}

/**
 * Coverage of requirements by planned/present test artefacts.
 * A requirement is covered when the test plan contains at least one case
 * linked to it, or a TEST-* artefact verifies it.
 */
export async function testCoverage(root) {
  const registry = await loadRequirementsRegistry(root);
  const plan = await loadTestPlan(root);
  const artefacts = await collectArtefactFiles(root);

  const plannedByReq = new Map();
  for (const tc of plan?.testCases ?? []) {
    if (!tc.requirement) continue;
    if (!plannedByReq.has(tc.requirement)) plannedByReq.set(tc.requirement, []);
    plannedByReq.get(tc.requirement).push(tc.id);
  }

  const verifiedByArtefact = new Set();
  for (const { body } of artefacts) {
    if (!String(body.id ?? '').startsWith('TEST-')) continue;
    for (const target of body.links?.['verified-by'] ?? body.links?.verifiedBy ?? []) {
      verifiedByArtefact.add(target);
    }
    for (const target of body.links?.implements ?? []) {
      verifiedByArtefact.add(target);
    }
  }

  const details = (registry.requirements ?? [])
    .filter((r) => r.status !== 'BLOCKED')
    .map((r) => ({
      requirement: r.id,
      acceptanceCriteria: r.acceptanceCriteria?.length ?? 0,
      plannedCases: plannedByReq.get(r.id) ?? [],
      hasTestArtefact: verifiedByArtefact.has(r.id),
      covered: (plannedByReq.get(r.id) ?? []).length > 0 || verifiedByArtefact.has(r.id),
    }));

  return {
    testPlan: plan?.id ?? null,
    total: details.length,
    covered: details.filter((d) => d.covered).length,
    uncovered: details.filter((d) => !d.covered).map((d) => d.requirement),
    details,
  };
}
