import path from 'node:path';
import { aidfPaths } from './paths.mjs';
import {
  readJson, writeJson, writeAtomic, ensureDir, exists, readText, sha256Text, toPosix,
} from '../util/fsx.mjs';
import { loadRequirementsRegistry } from './requirements/registry.mjs';
import { loadTestPlan } from './testplan.mjs';
import { loadThreatModel, renderThreatModelMarkdown } from './security/threat-model.mjs';
import { collectArtefactFiles } from './traceability/chain.mjs';

export const GENERATED_DIR = '.aidf/docs/generated';
export const GENERATED_INDEX = '.aidf/docs/generated/.generated.json';

function generatedHeader(sourceId, sourceHash) {
  return [
    '<!-- AIDF-GENERATED',
    `source: ${sourceId}`,
    `source-sha256: ${sourceHash}`,
    `generated-at: ${new Date().toISOString()}`,
    '-->',
    '',
  ].join('\n');
}

function renderRequirements(registry) {
  const lines = ['# Requirements', ''];
  lines.push(`Total: ${registry.requirements?.length ?? 0}`);
  lines.push('');
  lines.push('| ID | Type | Status | Priority | Title |');
  lines.push('|---|---|---|---|---|');
  for (const r of registry.requirements ?? []) {
    lines.push(`| ${r.id} | ${r.type} | ${r.status} | ${r.priority} | ${r.title} |`);
  }
  lines.push('');
  for (const r of registry.requirements ?? []) {
    const criteria = r.acceptanceCriteria ?? [];
    if (criteria.length === 0) continue;
    lines.push(`## ${r.id} — acceptance criteria`);
    lines.push('');
    for (const c of criteria) {
      const text = typeof c === 'string' ? c : (c.description ?? c.text ?? c.id);
      lines.push(`- ${text}`);
    }
    lines.push('');
  }
  return `${lines.join('\n')}\n`;
}

function renderTestPlan(plan) {
  if (!plan) return null;
  const lines = [`# Test Plan ${plan.id}`, ''];
  lines.push(`- Status: ${plan.status}`);
  lines.push(`- Cases: ${plan.testCases?.length ?? 0}`);
  lines.push(`- Covers: ${(plan.links?.covers ?? []).join(', ') || 'none'}`);
  lines.push('');
  lines.push('## Strategy');
  lines.push('');
  lines.push(plan.strategy?.approach ?? '');
  lines.push('');
  lines.push(`Mock policy: ${plan.strategy?.mockPolicy ?? 'n/a'}`);
  lines.push('');
  lines.push('## Test cases');
  lines.push('');
  lines.push('| ID | Type | Status | Requirement | Command |');
  lines.push('|---|---|---|---|---|');
  for (const tc of plan.testCases ?? []) {
    lines.push(`| ${tc.id} | ${tc.type} | ${tc.status} | ${tc.requirement ?? '-'} | \`${tc.command}\` |`);
  }
  lines.push('');
  return `${lines.join('\n')}\n`;
}

function renderDecisions(artefacts) {
  const decisions = [...artefacts.values()].filter((a) => a.type === 'decision');
  if (decisions.length === 0) return null;
  const lines = ['# Architecture Decisions', ''];
  for (const d of decisions.sort((a, b) => String(a.id).localeCompare(String(b.id)))) {
    lines.push(`## ${d.id} — ${d.title ?? d.id}`);
    lines.push('');
    lines.push(`- Status: ${d.status}`);
    if (d.document?.recommendation?.stack) {
      lines.push('- Recommendation:');
      for (const s of d.document.recommendation.stack) {
        lines.push(`  - ${s.component}: ${s.option}${s.reason ? ` — ${s.reason}` : ''}`);
      }
    }
    if (d.document?.humanApproval) {
      lines.push(`- Human approval required: ${d.document.humanApproval.required ? 'yes' : 'no'}`);
    }
    lines.push('');
  }
  return `${lines.join('\n')}\n`;
}

/**
 * Collect the generated Markdown documents for all canonical artefacts.
 * Returns [{ file, content, sourceId, sourceHash, sourcePath }].
 */
export async function collectGeneratedDocs(root) {
  const docs = [];
  const push = async (file, content, sourceId, sourcePath, sourceObj) => {
    if (content == null) return;
    const sourceHash = sha256Text(JSON.stringify(sourceObj ?? content));
    docs.push({ file, content, sourceId, sourceHash, sourcePath });
  };

  const registry = await loadRequirementsRegistry(root);
  await push('REQUIREMENTS.md', renderRequirements(registry), 'requirements',
    toPosix(aidfPaths(root).artefacts.requirements), registry);

  const plan = await loadTestPlan(root);
  await push('TEST-PLAN.md', renderTestPlan(plan), plan?.id ?? 'test-plan',
    toPosix(aidfPaths(root).artefacts.testPlan), plan);

  const tm = await loadThreatModel(root);
  if (tm) {
    await push('THREAT-MODEL.md', renderThreatModelMarkdown(tm), tm.id,
      toPosix(aidfPaths(root).artefacts['security/threat-model']), tm);
  }

  const artefacts = await collectArtefactFiles(root);
  const artefactMap = new Map([...artefacts].map((f) => [f.id, f.body]));
  await push('DECISIONS.md', renderDecisions(artefactMap), 'decisions',
    toPosix(aidfPaths(root).artefacts.decisions), [...artefactMap.values()].filter((a) => a.type === 'decision'));

  return docs;
}

/**
 * Generate (or check) Markdown views of canonical artefacts.
 * options.check: do not write; only report what would change and divergences.
 */
export async function generateDocs(root, options = {}) {
  const docs = await collectGeneratedDocs(root);
  const dir = path.join(root, GENERATED_DIR);
  const indexPath = path.join(root, GENERATED_INDEX);
  const previousIndex = options.check ? await readJson(indexPath, {}) : await readJson(indexPath, {});
  const previous = previousIndex.files ?? previousIndex;
  const results = [];

  for (const doc of docs) {
    const target = path.join(dir, doc.file);
    const content = `${generatedHeader(doc.sourceId, doc.sourceHash)}${doc.content}`;
    const existed = await exists(target);
    const previousOutputHash = previous[doc.file]?.outputHash ?? null;
    let diverged = false;
    if (existed && previousOutputHash) {
      const current = await readText(target);
      diverged = sha256Text(current) !== previousOutputHash;
    }
    let action = 'unchanged';
    if (options.check) {
      action = !existed ? 'missing' : diverged ? 'diverged' : 'current';
    } else {
      await ensureDir(dir);
      await writeAtomic(target, content);
      action = existed ? 'regenerated' : 'created';
    }
    results.push({
      file: toPosix(path.join(GENERATED_DIR, doc.file)),
      sourceId: doc.sourceId,
      action,
      diverged,
      manualEdit: diverged,
    });
    previous[doc.file] = { sourceId: doc.sourceId, sourceHash: doc.sourceHash, outputHash: sha256Text(content) };
  }

  if (!options.check && docs.length > 0) {
    await ensureDir(dir);
    await writeJson(indexPath, { generatedAt: new Date().toISOString(), files: { ...(previousIndex.files ?? {}), ...previous } });
  }

  return { generated: results, directory: GENERATED_DIR, checkedOnly: Boolean(options.check) };
}
