import path from 'node:path';
import fs from 'node:fs';
import fsp from 'node:fs/promises';
import crypto from 'node:crypto';
import { readText, readDirSafe, exists, toPosix, writeJson, readJson } from '../util/fsx.mjs';
import { validateSkillContent } from './skills.mjs';

/**
 * Resource Engine v2.
 *
 * The 0.1.0 engine inspected a resource and produced one tree hash over the
 * first 5000 files. This module adds three verified capabilities:
 *  - a file-level manifest (path, size, sha256) for every included file;
 *  - an explicit licence policy that decides indexing, copying and blocking;
 *  - a coverage report and a lock/manifest reconciliation.
 *
 * Security rules:
 *  - secret-like paths are never opened; they are counted and skipped;
 *  - .git, node_modules and declared excludes are skipped;
 *  - no file from a resource is ever executed.
 */

export const LICENSE_POLICIES = {
  MIT: {
    redistribution: 'allowed',
    copy: true,
    attribution: true,
    shareAlike: false,
    note: 'MIT permits copying and adaptation with the copyright notice.',
  },
  'Apache-2.0': {
    redistribution: 'allowed',
    copy: true,
    attribution: true,
    shareAlike: false,
    note: 'Apache-2.0 permits copying with NOTICE and attribution.',
  },
  'CC-BY-SA-4.0': {
    redistribution: 'allowed-with-attribution',
    copy: true,
    attribution: true,
    shareAlike: true,
    note: 'CC-BY-SA-4.0 requires attribution and share-alike for adaptations.',
  },
  'Licence Ouverte 2.0': {
    redistribution: 'allowed-with-attribution',
    copy: true,
    attribution: true,
    shareAlike: false,
    note: 'Licence Ouverte 2.0 permits reuse, including commercial, with attribution.',
  },
  'GPL-2.0': {
    redistribution: 'blocked-in-mit-distribution',
    copy: false,
    attribution: true,
    shareAlike: true,
    note: 'GPL-2.0 code cannot be bundled into the MIT AIDF package.',
  },
  'proprietary-owner': {
    redistribution: 'no',
    copy: false,
    attribution: false,
    shareAlike: false,
    note: 'Owner-restricted corpus: index only, never redistribute.',
  },
  unknown: {
    redistribution: 'blocked',
    copy: false,
    attribution: false,
    shareAlike: false,
    note: 'No verified licence: index only until the owner clarifies it.',
  },
};

const LICENSE_ALIASES = new Map([
  ['mit', 'MIT'],
  ['mit license', 'MIT'],
  ['apache-2.0', 'Apache-2.0'],
  ['apache 2.0', 'Apache-2.0'],
  ['cc-by-sa-4.0', 'CC-BY-SA-4.0'],
  ['cc by-sa 4.0', 'CC-BY-SA-4.0'],
  ['licence ouverte 2.0', 'Licence Ouverte 2.0'],
  ['licence ouverte 2.0/open licence 2.0', 'Licence Ouverte 2.0'],
  ['gpl-2.0', 'GPL-2.0'],
  ['gpl-2.0-only', 'GPL-2.0'],
  ['proprietary-owner', 'proprietary-owner'],
  ['unknown', 'unknown'],
  ['', 'unknown'],
]);

export function normalizeLicense(license) {
  if (!license) return 'unknown';
  const key = String(license).trim().toLowerCase();
  return LICENSE_ALIASES.get(key) ?? 'unknown';
}

export function licensePolicy(license) {
  const id = normalizeLicense(license);
  return { id, ...LICENSE_POLICIES[id] };
}

export function isSecretPath(relPath) {
  const posix = toPosix(relPath);
  const base = posix.split('/').pop() ?? posix;
  return (
    /^\.env(\.|$)/i.test(base)
    || /\.(pem|p12|pfx|crt|key|keystore|jks)$/i.test(base)
    || /(^|\/)(id_rsa|id_ed25519|credentials|secrets?\.ya?ml)$/i.test(posix)
    || /(^|\/)\.npmrc$/i.test(posix)
    || /(^|\/)\.git-credentials$/i.test(posix)
  );
}

function globToRegExp(glob) {
  let out = '^';
  for (let i = 0; i < glob.length; i += 1) {
    const ch = glob[i];
    if (ch === '*') {
      if (glob[i + 1] === '*') {
        out += '.*';
        i += 1;
        if (glob[i + 1] === '/') i += 1;
      } else {
        out += '[^/]*';
      }
    } else if (ch === '?') {
      out += '[^/]';
    } else if ('\\^$.|+()[]{}'.includes(ch)) {
      out += `\\${ch}`;
    } else {
      out += ch;
    }
  }
  return new RegExp(`${out}$`);
}

export function matchesAny(relPath, globs = []) {
  const posix = toPosix(relPath);
  return globs.some((g) => globToRegExp(g).test(posix));
}

export function selectFiles(resource, files) {
  const include = resource.include ?? [];
  const exclude = resource.exclude ?? [];
  return files.filter((rel) => {
    if (matchesAny(rel, exclude)) return false;
    if (isSecretPath(rel)) return false;
    return include.length === 0 || matchesAny(rel, include);
  });
}

async function listAllFiles(dir) {
  const files = [];
  async function walk(current, rel) {
    for (const entry of await readDirSafe(current)) {
      if (entry.name === '.git' || entry.name === 'node_modules') continue;
      const childRel = rel ? `${rel}/${entry.name}` : entry.name;
      const childAbs = path.join(current, entry.name);
      if (entry.isDirectory()) await walk(childAbs, childRel);
      else if (entry.isFile()) files.push(childRel);
    }
  }
  await walk(dir, '');
  return files.sort();
}

async function hashFileStreaming(file) {
  const hash = crypto.createHash('sha256');
  const handle = await fsp.open(file, 'r');
  try {
    const buffer = Buffer.allocUnsafe(1024 * 1024);
    let position = 0;
    for (;;) {
      const { bytesRead } = await handle.read(buffer, 0, buffer.length, position);
      if (bytesRead === 0) break;
      position += bytesRead;
      hash.update(buffer.subarray(0, bytesRead));
    }
  } finally {
    await handle.close();
  }
  return hash.digest('hex');
}

async function mapLimit(items, limit, worker) {
  const results = new Array(items.length);
  let cursor = 0;
  const runners = Array.from({ length: Math.min(limit, items.length) }, async () => {
    for (;;) {
      const index = cursor;
      cursor += 1;
      if (index >= items.length) return;
      results[index] = await worker(items[index], index);
    }
  });
  await Promise.all(runners);
  return results;
}

function resolveResourceDir(resource, roots) {
  const candidates = [];
  if (resource.localPath) {
    if (path.isAbsolute(resource.localPath)) candidates.push(resource.localPath);
    else for (const root of roots) candidates.push(path.resolve(root, resource.localPath));
  }
  return candidates;
}

export async function inventoryResource(resource, options = {}) {
  const roots = options.roots ?? [path.dirname(options.frameworkRoot ?? process.cwd())];
  const maxFileBytes = options.maxFileBytes ?? 64 * 1024 * 1024;
  const concurrency = Math.max(1, Math.min(8, options.concurrency ?? 4));
  const policy = licensePolicy(resource.license);
  const base = {
    id: resource.id,
    name: resource.name,
    kind: resource.kind,
    origin: resource.origin,
    license: policy.id,
    licensePolicy: {
      redistribution: policy.redistribution,
      copy: policy.copy,
      attribution: policy.attribution,
      shareAlike: policy.shareAlike,
    },
    install: resource.install ?? 'reference',
  };

  if (resource.kind === 'reference' || !resource.localPath) {
    return {
      ...base,
      status: resource.status ?? 'NOT_INSTALLED',
      path: null,
      fileCount: 0,
      totalBytes: 0,
      treeHash: null,
      files: [],
      skills: [],
      skipped: { secrets: 0, excluded: 0, oversized: 0 },
      note: 'Reference-only resource; nothing is copied or hashed.',
    };
  }

  const dir = resolveResourceDir(resource, roots).find((candidate) => existsSync(candidate));
  if (!dir) {
    return {
      ...base,
      status: 'ABSENT',
      path: null,
      fileCount: 0,
      totalBytes: 0,
      treeHash: null,
      files: [],
      skills: [],
      skipped: { secrets: 0, excluded: 0, oversized: 0 },
      note: 'No local copy found in the configured candidate paths.',
    };
  }

  const all = await listAllFiles(dir);
  const secretCount = all.filter((rel) => isSecretPath(rel)).length;
  const included = selectFiles(resource, all);
  const skippedExcluded = all.length - included.length - secretCount;

  const entries = [];
  const oversized = [];
  await mapLimit(included, concurrency, async (rel) => {
    const abs = path.join(dir, rel);
    const stat = await fsp.stat(abs);
    if (stat.size > maxFileBytes) {
      oversized.push(rel);
      return;
    }
    const hash = await hashFileStreaming(abs);
    entries.push({ path: rel, size: stat.size, sha256: hash });
  });
  entries.sort((a, b) => (a.path < b.path ? -1 : a.path > b.path ? 1 : 0));

  const treeHash = crypto.createHash('sha256')
    .update(entries.map((e) => `${e.path}:${e.sha256}`).join('\n'))
    .digest('hex');

  const skills = [];
  for (const entry of entries) {
    if (!entry.path.endsWith('SKILL.md')) continue;
    const content = await readText(path.join(dir, entry.path));
    const validation = validateSkillContent(content, {
      directoryName: path.basename(path.dirname(entry.path)),
    });
    skills.push({
      name: validation.name ?? path.basename(path.dirname(entry.path)),
      description: validation.description ?? '',
      path: entry.path,
      sha256: entry.sha256,
      valid: validation.valid,
      errors: validation.errors,
      warnings: validation.warnings,
    });
  }

  const totalBytes = entries.reduce((sum, entry) => sum + entry.size, 0);
  let status = policy.redistribution === 'blocked' || policy.copy === false
    ? (policy.id === 'proprietary-owner' ? 'PARTIAL' : 'BLOCKED')
    : 'PRESENT';
  if (included.length === 0) status = 'PARTIAL';

  return {
    ...base,
    status,
    path: toPosix(dir),
    commit: resource.commit ?? null,
    fileCount: entries.length,
    totalBytes,
    treeHash,
    files: entries,
    skills,
    skipped: { secrets: secretCount, excluded: skippedExcluded, oversized: oversized.length },
    note: policy.id === 'unknown'
      ? 'Licence is unknown: index-only, not redistributable.'
      : policy.copy === false
        ? policy.note
        : policy.note,
    oversizedFiles: oversized,
  };
}

function existsSync(candidate) {
  try {
    return fs.statSync(candidate).isDirectory();
  } catch {
    return false;
  }
}

export async function inventoryAll(sources, options = {}) {
  const results = [];
  for (const resource of sources.resources ?? []) {
    results.push(await inventoryResource(resource, options));
  }
  return results;
}

export function summarizeInventory(results) {
  const byStatus = {};
  const totals = {
    resources: results.length,
    files: 0,
    bytes: 0,
    skills: 0,
    validSkills: 0,
    secretsSkipped: 0,
    oversized: 0,
  };
  for (const result of results) {
    byStatus[result.status] = (byStatus[result.status] ?? 0) + 1;
    totals.files += result.fileCount ?? 0;
    totals.bytes += result.totalBytes ?? 0;
    totals.skills += result.skills?.length ?? 0;
    totals.validSkills += (result.skills ?? []).filter((s) => s.valid).length;
    totals.secretsSkipped += result.skipped?.secrets ?? 0;
    totals.oversized += result.skipped?.oversized ?? 0;
  }
  return { byStatus, ...totals };
}

export function coverageReport(sources, results) {
  const declared = (sources.resources ?? []).map((r) => r.id);
  const inventoried = new Map(results.map((r) => [r.id, r]));
  const missing = declared.filter((id) => !inventoried.has(id));
  const skillNames = new Map();
  for (const result of results) {
    for (const skill of result.skills ?? []) {
      const list = skillNames.get(skill.name) ?? [];
      list.push({ resource: result.id, path: skill.path, sha256: skill.sha256, valid: skill.valid });
      skillNames.set(skill.name, list);
    }
  }
  const duplicates = [...skillNames.entries()]
    .filter(([, list]) => list.length > 1)
    .map(([name, list]) => ({
      name,
      count: list.length,
      identical: new Set(list.map((s) => s.sha256)).size === 1,
      locations: list.map((s) => `${s.resource}:${s.path}`),
    }));
  const licenses = {};
  for (const result of results) licenses[result.license] = (licenses[result.license] ?? 0) + 1;
  return {
    declaredResources: declared.length,
    inventoriedResources: results.length,
    missing,
    complete: missing.length === 0 && results.length === declared.length,
    licenses,
    duplicateSkills: duplicates,
    uniqueSkills: skillNames.size,
    totalSkillFiles: [...skillNames.values()].reduce((sum, list) => sum + list.length, 0),
    blockedResources: results.filter((r) => r.status === 'BLOCKED').map((r) => r.id),
    absentResources: results.filter((r) => r.status === 'ABSENT').map((r) => r.id),
  };
}

export async function writeInventory(root, results) {
  const file = path.join(root, '.aidf', 'registry', 'resources.manifest.json');
  const payload = {
    schemaVersion: 1,
    generatedAt: new Date().toISOString(),
    note: 'File-level manifest. Secret-like paths are never hashed or listed.',
    resources: results,
  };
  await writeJson(file, payload);
  return { file: toPosix(file), payload };
}

export async function readInventory(root) {
  return readJson(path.join(root, '.aidf', 'registry', 'resources.manifest.json'), null);
}

export function reconcile(lock, manifest) {
  const locked = lock?.resources ?? {};
  const current = new Map((manifest?.resources ?? []).map((r) => [r.id, r]));
  const drift = [];
  for (const [id, entry] of Object.entries(locked)) {
    const now = current.get(id);
    if (!now) {
      drift.push({ id, kind: 'MISSING', expected: entry.treeHash ?? null, actual: null });
      continue;
    }
    if (entry.treeHash && now.treeHash && entry.treeHash !== now.treeHash) {
      drift.push({ id, kind: 'CHANGED', expected: entry.treeHash, actual: now.treeHash });
    }
  }
  const unchanged = Object.keys(locked).filter((id) => !drift.some((d) => d.id === id));
  return { drift, unchanged, checked: Object.keys(locked).length };
}
