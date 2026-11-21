import path from 'node:path';
import fsp from 'node:fs/promises';
import { ensureDir, toPosix, exists, resolveInside, writeJson } from '../util/fsx.mjs';
import { licensePolicy, isSecretPath } from './resource-inventory.mjs';

/**
 * Skills Engine v2 — source skill catalogue.
 *
 * 0.1.0 only knew the 13 built-in skills. This module turns the verified
 * resource inventory into a catalogue of every third-party SKILL.md, with an
 * explicit status derived from licence policy and validation. Nothing is
 * copied or executed implicitly: enabling a skill is an explicit command.
 */

export const SOURCE_SKILL_STATUSES = [
  'AVAILABLE', 'INDEX_ONLY', 'BLOCKED', 'DUPLICATE', 'INCOMPATIBLE',
];

const EXECUTABLE_RE = /\.(sh|bash|ps1|bat|cmd|exe|com|msi|jar|py|rb|pl)$/i;

export function catalogSourceSkills(inventoryResults) {
  const entries = [];
  for (const resource of inventoryResults) {
    const policy = licensePolicy(resource.license);
    for (const skill of resource.skills ?? []) {
      let status;
      let reason;
      if (!skill.valid) {
        status = 'INCOMPATIBLE';
        reason = (skill.errors ?? []).join('; ') || 'Invalid SKILL.md.';
      } else if (policy.redistribution === 'blocked' || policy.id === 'unknown') {
        status = 'INDEX_ONLY';
        reason = 'Licence unknown or blocked: indexed by reference, never copied.';
      } else if (policy.copy !== true) {
        status = 'INDEX_ONLY';
        reason = policy.note;
      } else {
        status = 'AVAILABLE';
        reason = 'Permissive licence: copy allowed with attribution.';
      }
      entries.push({
        id: skill.name,
        name: skill.name,
        resource: resource.id,
        resourcePath: resource.path,
        license: policy.id,
        redistribution: policy.redistribution,
        path: skill.path,
        sha256: skill.sha256,
        description: skill.description,
        valid: skill.valid,
        status,
        reason,
      });
    }
  }
  return markDuplicates(entries);
}

export function markDuplicates(entries) {
  const byName = new Map();
  for (const entry of entries) {
    const list = byName.get(entry.id) ?? [];
    list.push(entry);
    byName.set(entry.id, list);
  }
  for (const [name, list] of byName) {
    if (list.length < 2) continue;
    const canonical = pickCanonical(list);
    for (const entry of list) {
      if (entry === canonical) {
        entry.canonical = true;
        entry.duplicateOf = null;
        continue;
      }
      entry.canonical = false;
      entry.duplicateOf = `${canonical.resource}:${canonical.path}`;
      if (entry.status === 'AVAILABLE') {
        entry.status = 'DUPLICATE';
        entry.reason = `Duplicate of ${canonical.resource}:${canonical.path}.`;
      }
    }
    void name;
  }
  return entries;
}

function pickCanonical(list) {
  const rank = (entry) => {
    const copy = entry.status === 'AVAILABLE' ? 0 : 1;
    const valid = entry.valid ? 0 : 1;
    return copy * 10 + valid * 5 + entry.path.length / 1000;
  };
  return [...list].sort((a, b) => rank(a) - rank(b))[0];
}

const DEFAULT_TRIGGERS = {
  security: ['owasp', 'security', 'audit', 'vulnerability', 'threat'],
  accessibility: ['rgaa', 'accessib', 'a11y', 'wcag'],
  design: ['design', 'ui', 'ux', 'wireframe', 'figma'],
  testing: ['test', 'tdd', 'verification'],
  documentation: ['docs', 'documentation', 'article'],
};

/**
 * Select source skills relevant to a task. Selection is deterministic:
 * it lowercases the task, matches known triggers, and never picks a blocked
 * or index-only skill for execution.
 */
export function selectSourceSkills(entries, task = '', options = {}) {
  const text = String(task).toLowerCase();
  const limit = options.limit ?? 12;
  const matched = [];
  for (const entry of entries) {
    if (entry.status !== 'AVAILABLE' && entry.status !== 'DUPLICATE') continue;
    const haystack = `${entry.id} ${entry.description}`.toLowerCase();
    let score = 0;
    for (const [group, keywords] of Object.entries(DEFAULT_TRIGGERS)) {
      if (!keywords.some((k) => text.includes(k))) continue;
      if (keywords.some((k) => haystack.includes(k)) || haystack.includes(group)) score += 2;
    }
    if (text && haystack.includes(text.trim())) score += 3;
    if (score > 0) matched.push({ ...entry, score });
  }
  return matched.sort((a, b) => b.score - a.score || a.id.localeCompare(b.id)).slice(0, limit);
}

export function summarizeSourceSkills(entries) {
  const summary = {};
  for (const status of SOURCE_SKILL_STATUSES) summary[status] = 0;
  for (const entry of entries) summary[entry.status] = (summary[entry.status] ?? 0) + 1;
  summary.total = entries.length;
  summary.unique = new Set(entries.map((e) => e.id)).size;
  summary.canonicalAvailable = entries.filter((e) => e.canonical && e.status === 'AVAILABLE').length;
  return summary;
}

function isExecutable(rel) {
  return EXECUTABLE_RE.test(rel);
}

/**
 * Copy one source skill directory into a target directory.
 * Only regular support files are copied; executable scripts are refused unless
 * allowScripts is explicitly true. Secret-like files are never copied.
 */
export async function copySourceSkill(entry, inventory, targetDir, options = {}) {
  if (!entry || entry.status === 'INDEX_ONLY' || entry.status === 'BLOCKED') {
    return { status: 'BLOCKED', reason: entry?.reason ?? 'Skill is not copyable.' };
  }
  const resource = inventory.resources.find((r) => r.id === entry.resource);
  if (!resource || !resource.path) {
    return { status: 'BLOCKED', reason: 'Resource path is unknown.' };
  }
  if (!resource.licensePolicy?.copy) {
    return { status: 'BLOCKED', reason: `Licence ${resource.license} does not allow copying.` };
  }
  const skillDir = path.posix.dirname(entry.path);
  const prefix = `${skillDir}/`;
  const members = resource.files.filter((file) => file.path.startsWith(prefix));
  const copied = [];
  const skipped = [];
  for (const file of members) {
    if (isSecretPath(file.path)) {
      skipped.push({ path: file.path, reason: 'secret' });
      continue;
    }
    if (isExecutable(file.path) && !options.allowScripts) {
      skipped.push({ path: file.path, reason: 'executable' });
      continue;
    }
    const rel = file.path.slice(prefix.length);
    const destination = path.join(targetDir, rel);
    await ensureDir(path.dirname(destination));
    await fsp.copyFile(path.join(resource.path, file.path), destination);
    copied.push(toPosix(destination));
  }
  await writeJson(path.join(targetDir, 'AIDF-SOURCE.json'), {
    schemaVersion: 1,
    resource: entry.resource,
    skill: entry.id,
    license: entry.license,
    sourcePath: entry.path,
    sha256: entry.sha256,
    copiedAt: new Date().toISOString(),
    scriptsCopied: Boolean(options.allowScripts),
    note: 'Copied from an external resource. The resource remains untrusted data.',
  });
  return { status: 'COPIED', target: toPosix(targetDir), files: copied.length, skipped };
}

export async function loadSourceSkillCatalog(root) {
  const file = path.join(root, '.aidf', 'registry', 'source-skills.json');
  return (await exists(file)) ? JSON.parse(await fsp.readFile(file, 'utf8')) : null;
}

export async function saveSourceSkillCatalog(root, entries) {
  const file = path.join(root, '.aidf', 'registry', 'source-skills.json');
  const payload = {
    schemaVersion: 1,
    generatedAt: new Date().toISOString(),
    summary: summarizeSourceSkills(entries),
    skills: entries,
  };
  await writeJson(file, payload);
  return payload;
}

export function skillInstallTarget(root, adapter, id) {
  if (adapter === 'opencode') return resolveInside(root, path.posix.join('.opencode', 'skills', id));
  if (adapter === 'qoder') return resolveInside(root, path.posix.join('.qoder', 'skills', id));
  return resolveInside(root, path.posix.join('.aidf', 'skills', id));
}
