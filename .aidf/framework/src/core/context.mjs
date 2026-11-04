import path from 'node:path';
import { aidfPaths } from './paths.mjs';
import { loadConfig } from './config.mjs';
import { loadActivationMap, loadSkillCatalog, buildSkillRegistry, loadProjectSkillRegistry } from './skills.mjs';
import { loadPhases, loadWorkflowState, phaseById } from './workflow.mjs';
import { writeAtomic, ensureDir } from '../util/fsx.mjs';
import { timestampSlug } from '../util/fsx.mjs';

function words(text) {
  return String(text ?? '')
    .toLowerCase()
    .split(/[^a-z0-9\u00C0-\u024F]+/)
    .filter((w) => w.length >= 3);
}

function matchesKeyword(taskLower, keyword) {
  const k = String(keyword).toLowerCase();
  if (k.includes(' ')) return taskLower.includes(k);
  return new RegExp(`(^|[^a-z0-9])${k.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}($|[^a-z0-9])`).test(taskLower);
}

function addCandidate(map, id, kind, reason, score = 1) {
  const existing = map.get(id);
  if (existing) {
    existing.score += score;
    if (!existing.reasons.includes(reason)) existing.reasons.push(reason);
    return;
  }
  map.set(id, { id, kind, score, reasons: [reason] });
}

export async function assembleContext(root, task, options = {}) {
  const config = options.config ?? (await loadConfig(root));
  const activation = options.activation ?? (await loadActivationMap(options.frameworkRoot));
  const phases = options.phases ?? (await loadPhases(options.frameworkRoot));
  const state = options.state ?? (await loadWorkflowState(root));
  const phase = phaseById(phases, state.currentPhase);
  const taskLower = String(task ?? '').toLowerCase();

  let registry = options.registry;
  if (!registry) {
    const saved = await loadProjectSkillRegistry(root);
    registry = saved.skills?.length ? saved : await buildSkillRegistry(root, { adapter: config.adapter });
  }
  const skillById = new Map(registry.skills.map((s) => [s.id, s]));
  const skillByName = new Map(registry.skills.map((s) => [s.name, s]));

  const docCandidates = new Map();
  const skillCandidates = new Map();

  for (const doc of activation.always?.documents ?? []) addCandidate(docCandidates, doc, 'document', 'always-on');
  for (const skill of activation.always?.skills ?? []) addCandidate(skillCandidates, skill, 'skill', 'always-on');

  if (phase) {
    for (const doc of phase.requiredDocuments ?? []) addCandidate(docCandidates, doc, 'document', `phase:${phase.id}`, 3);
    for (const skill of phase.skills ?? []) addCandidate(skillCandidates, skill, 'skill', `phase:${phase.id}`, 3);
  }

  for (const rule of activation.byKeyword ?? []) {
    const hit = (rule.keywords ?? []).some((k) => matchesKeyword(taskLower, k));
    if (!hit) continue;
    const matched = (rule.keywords ?? []).filter((k) => matchesKeyword(taskLower, k));
    for (const doc of rule.documents ?? []) addCandidate(docCandidates, doc, 'document', `keyword:${matched.join('|')}`, 2);
    for (const skill of rule.skills ?? []) addCandidate(skillCandidates, skill, 'skill', `keyword:${matched.join('|')}`, 2);
  }

  for (const doc of activation.contradictionSensitive ?? []) addCandidate(docCandidates, doc, 'document', 'contradiction-sensitive', 1);

  const alwaysDocs = new Set(activation.always?.documents ?? []);
  const sortedDocs = [...docCandidates.values()]
    .sort((a, b) => (alwaysDocs.has(b.id) ? 1 : 0) - (alwaysDocs.has(a.id) ? 1 : 0) || b.score - a.score);
  const maxDocs = config.context?.maxDocuments ?? 8;
  const selectedDocs = [];
  for (const candidate of sortedDocs) {
    if (selectedDocs.length >= maxDocs && !alwaysDocs.has(candidate.id)) continue;
    const entry = config.documents?.[candidate.id];
    selectedDocs.push({
      ...candidate,
      status: entry?.status ?? 'MISSING',
      path: `.aidf/docs/${candidate.id}.md`,
      exists: entry !== undefined,
    });
  }

  const alwaysSkills = new Set(activation.always?.skills ?? []);
  const sortedSkills = [...skillCandidates.values()]
    .sort((a, b) => (alwaysSkills.has(b.id) ? 1 : 0) - (alwaysSkills.has(a.id) ? 1 : 0) || b.score - a.score);
  const maxSkills = config.context?.maxSkills ?? config.skills?.maxActive ?? 5;
  const selectedSkills = [];
  for (const candidate of sortedSkills) {
    if (selectedSkills.length >= maxSkills && !alwaysSkills.has(candidate.id)) continue;
    const skill = skillById.get(candidate.id) ?? skillByName.get(candidate.id);
    selectedSkills.push({
      ...candidate,
      name: skill?.name ?? candidate.id,
      status: skill?.status ?? 'MISSING',
      path: skill?.path ?? null,
      available: ['AVAILABLE', 'REGISTERED', 'VALIDATED'].includes(skill?.status),
      description: skill?.description ?? '',
    });
  }

  const contradictions = [];
  for (const doc of activation.contradictionSensitive ?? []) {
    const entry = config.documents?.[doc];
    if (entry && ['BLOCKED', 'OUTDATED'].includes(entry.status)) {
      contradictions.push({
        document: doc,
        status: entry.status,
        effect: 'Decisions depending on this document are BLOCKED until resolved.',
        note: entry.note ?? '',
      });
    }
  }

  const result = {
    task,
    phase: state.currentPhase,
    phaseName: phase?.name ?? null,
    generatedAt: new Date().toISOString(),
    documents: selectedDocs,
    skills: selectedSkills,
    contradictions,
    status: contradictions.length > 0 ? 'BLOCKED' : 'OK',
    notes: [
      'Read .aidf/AIDF.md and .aidf/docs/constitution.md first.',
      'Load only the selected documents and skills.',
      'A document marked BLOCKED or OUTDATED blocks decisions that depend on it.',
    ],
  };
  return result;
}

export function renderContextMarkdown(context, config) {
  const lines = [];
  lines.push(`# AIDF Context — ${context.phase}`);
  lines.push('');
  lines.push(`- Task: ${context.task || '(not provided)'}`);
  lines.push(`- Phase: ${context.phase}${context.phaseName ? ` — ${context.phaseName}` : ''}`);
  lines.push(`- Generated: ${context.generatedAt}`);
  lines.push(`- Status: ${context.status}`);
  lines.push('');
  lines.push('## Read in this order');
  lines.push('');
  lines.push('1. `.aidf/AIDF.md`');
  lines.push('2. `.aidf/docs/constitution.md`');
  let docNumber = 3;
  context.documents.forEach((doc) => {
    if (doc.id === 'constitution') return;
    lines.push(`${docNumber}. \`${doc.path}\` — ${doc.status} (${doc.reasons.join(', ')})`);
    docNumber += 1;
  });
  lines.push('');
  lines.push('## Selected skills');
  lines.push('');
  if (context.skills.length === 0) lines.push('_None._');
  for (const skill of context.skills) {
    lines.push(`- **${skill.name}** — ${skill.status}${skill.path ? ` — \`${skill.path}\`` : ''}`);
    lines.push(`  - Why: ${skill.reasons.join(', ')}`);
  }
  lines.push('');
  lines.push('## Contradictions and blockers');
  lines.push('');
  if (context.contradictions.length === 0) lines.push('_None detected._');
  for (const c of context.contradictions) {
    lines.push(`- **${c.document}** is ${c.status}. ${c.effect}${c.note ? ` Note: ${c.note}` : ''}`);
  }
  lines.push('');
  lines.push('## Operating reminders');
  lines.push('');
  for (const note of context.notes) lines.push(`- ${note}`);
  lines.push(`- Security policy: command execution ${config.security?.allowCommandExecution ? 'ENABLED' : 'disabled by default'}; network ${config.security?.allowNetwork ? 'ENABLED' : 'disabled by default'}.`);
  lines.push('');
  return `${lines.join('\n')}\n`;
}

export async function writeContext(root, context, config) {
  const paths = aidfPaths(root);
  await ensureDir(paths.context);
  const markdown = renderContextMarkdown(context, config);
  await writeAtomic(path.join(paths.context, 'latest.md'), markdown);
  await writeAtomic(path.join(paths.context, `${timestampSlug()}.json`), `${JSON.stringify(context, null, 2)}\n`);
  return {
    markdownPath: '.aidf/state/context/latest.md',
    markdown,
  };
}
