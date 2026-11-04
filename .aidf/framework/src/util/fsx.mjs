import fs from 'node:fs';
import fsp from 'node:fs/promises';
import path from 'node:path';
import crypto from 'node:crypto';

const WINDOWS_RESERVED = new Set([
  'con', 'prn', 'aux', 'nul',
  'com1', 'com2', 'com3', 'com4', 'com5', 'com6', 'com7', 'com8', 'com9',
  'lpt1', 'lpt2', 'lpt3', 'lpt4', 'lpt5', 'lpt6', 'lpt7', 'lpt8', 'lpt9',
]);

export class PathSafetyError extends Error {
  constructor(message) {
    super(message);
    this.name = 'PathSafetyError';
  }
}

/** Convert a project-relative path to a safe absolute path inside root. */
export function resolveInside(root, relativePath) {
  if (typeof relativePath !== 'string' || relativePath.trim() === '') {
    throw new PathSafetyError('Path must be a non-empty string');
  }
  if (relativePath.includes('\0')) {
    throw new PathSafetyError('Path contains a null byte');
  }
  if (path.isAbsolute(relativePath) || /^[A-Za-z]:/.test(relativePath)) {
    throw new PathSafetyError(`Absolute paths are not allowed: ${relativePath}`);
  }
  const normalized = path.normalize(relativePath).replace(/\\/g, '/');
  if (normalized === '..' || normalized.startsWith('../') || normalized.includes('/../')) {
    throw new PathSafetyError(`Path traversal is not allowed: ${relativePath}`);
  }
  const rootResolved = path.resolve(root);
  const target = path.resolve(rootResolved, normalized);
  const rel = path.relative(rootResolved, target);
  if (rel === '' || rel.startsWith('..') || path.isAbsolute(rel)) {
    throw new PathSafetyError(`Path escapes project root: ${relativePath}`);
  }
  for (const segment of normalized.split('/')) {
    const base = segment.split('.')[0].toLowerCase();
    if (WINDOWS_RESERVED.has(base)) {
      throw new PathSafetyError(`Reserved Windows path segment: ${segment}`);
    }
    if (/[<>:"|?*]/.test(segment)) {
      throw new PathSafetyError(`Invalid path character in: ${segment}`);
    }
  }
  return target;
}

export function toPosix(p) {
  return p.replace(/\\/g, '/');
}

export function relativeTo(root, target) {
  return toPosix(path.relative(path.resolve(root), path.resolve(target)));
}

export async function exists(p) {
  try {
    await fsp.access(p);
    return true;
  } catch {
    return false;
  }
}

export function existsSync(p) {
  try {
    fs.accessSync(p);
    return true;
  } catch {
    return false;
  }
}

export async function ensureDir(dir) {
  await fsp.mkdir(dir, { recursive: true });
}

export async function readText(p) {
  return fsp.readFile(p, 'utf8');
}

export async function readJson(p, fallback = null) {
  try {
    const text = await fsp.readFile(p, 'utf8');
    return JSON.parse(text);
  } catch (error) {
    if (error && error.code === 'ENOENT') return fallback;
    throw error;
  }
}

export async function writeAtomic(p, content) {
  await ensureDir(path.dirname(p));
  const tmp = `${p}.tmp-${process.pid}-${crypto.randomBytes(4).toString('hex')}`;
  await fsp.writeFile(tmp, content, 'utf8');
  await fsp.rename(tmp, p);
}

export async function writeJson(p, value) {
  await writeAtomic(p, `${JSON.stringify(value, null, 2)}\n`);
}

export function sha256Buffer(buffer) {
  return crypto.createHash('sha256').update(buffer).digest('hex');
}

export function sha256Text(text) {
  return sha256Buffer(Buffer.from(text, 'utf8'));
}

export async function sha256File(p) {
  const buffer = await fsp.readFile(p);
  return sha256Buffer(buffer);
}

export async function listFiles(root, options = {}) {
  const { ignore = [], maxFiles = 20000 } = options;
  const results = [];
  const ignoreSet = new Set(ignore.map((p) => toPosix(p)));
  async function walk(dir, rel) {
    if (results.length >= maxFiles) return;
    let entries;
    try {
      entries = await fsp.readdir(dir, { withFileTypes: true });
    } catch {
      return;
    }
    for (const entry of entries) {
      const childRel = rel ? `${rel}/${entry.name}` : entry.name;
      if (ignoreSet.has(childRel)) continue;
      if (entry.name === '.git' || entry.name === 'node_modules') continue;
      const childAbs = path.join(dir, entry.name);
      if (entry.isDirectory()) {
        await walk(childAbs, childRel);
      } else if (entry.isFile()) {
        results.push(childRel);
      }
      if (results.length >= maxFiles) return;
    }
  }
  await walk(path.resolve(root), '');
  return results;
}

export async function copyFileSafe(source, target) {
  await ensureDir(path.dirname(target));
  await fsp.copyFile(source, target);
}

export async function removeFile(p) {
  try {
    await fsp.unlink(p);
    return true;
  } catch (error) {
    if (error && error.code === 'ENOENT') return false;
    throw error;
  }
}

export async function removeDir(p) {
  try {
    await fsp.rm(p, { recursive: true, force: false });
    return true;
  } catch (error) {
    if (error && error.code === 'ENOENT') return false;
    throw error;
  }
}

export async function readDirSafe(p) {
  try {
    return await fsp.readdir(p, { withFileTypes: true });
  } catch {
    return [];
  }
}

export function timestampSlug(date = new Date()) {
  return date.toISOString().replace(/[:.]/g, '-');
}

export function shortHash(text, length = 12) {
  return sha256Text(text).slice(0, length);
}

/** Recursively merge patch into base. Existing scalar values in base win unless overwrite is true. */
export function deepMerge(base, patch, overwrite = false) {
  if (Array.isArray(base) || Array.isArray(patch)) {
    return patch === undefined ? base : patch;
  }
  if (base && typeof base === 'object' && patch && typeof patch === 'object') {
    const out = { ...base };
    for (const [key, value] of Object.entries(patch)) {
      if (!(key in out)) out[key] = value;
      else if (out[key] && typeof out[key] === 'object' && value && typeof value === 'object') {
        out[key] = deepMerge(out[key], value, overwrite);
      } else if (overwrite) {
        out[key] = value;
      }
    }
    return out;
  }
  return base === undefined ? patch : base;
}
