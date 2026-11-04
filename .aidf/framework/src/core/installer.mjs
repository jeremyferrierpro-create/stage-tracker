import path from 'node:path';
import fsp from 'node:fs/promises';
import {
  resolveInside, exists, ensureDir, readText, writeAtomic, sha256Text, sha256File,
  timestampSlug, toPosix,
} from '../util/fsx.mjs';
import { replaceManagedBlock } from '../util/text.mjs';
import { recordFile, fileRecord } from './manifest.mjs';

export const ACTION_STATUS = {
  CREATED: 'CREATED',
  UPDATED: 'UPDATED',
  UNCHANGED: 'UNCHANGED',
  CONFLICT: 'CONFLICT',
  LINKED: 'LINKED',
  WOULD_CREATE: 'WOULD_CREATE',
  WOULD_UPDATE: 'WOULD_UPDATE',
  WOULD_CONFLICT: 'WOULD_CONFLICT',
  WOULD_LINK: 'WOULD_LINK',
  FAILED: 'FAILED',
};

function stripJsonComments(text) {
  let out = '';
  let inString = false;
  let escaped = false;
  for (let i = 0; i < text.length; i += 1) {
    const ch = text[i];
    const next = text[i + 1];
    if (inString) {
      out += ch;
      if (escaped) escaped = false;
      else if (ch === '\\') escaped = true;
      else if (ch === '"') inString = false;
      continue;
    }
    if (ch === '"') {
      inString = true;
      out += ch;
      continue;
    }
    if (ch === '/' && next === '/') {
      while (i < text.length && text[i] !== '\n') i += 1;
      out += '\n';
      continue;
    }
    if (ch === '/' && next === '*') {
      i += 2;
      while (i < text.length && !(text[i] === '*' && text[i + 1] === '/')) i += 1;
      i += 1;
      continue;
    }
    out += ch;
  }
  return out;
}

function parseJsonLoose(text) {
  try {
    return JSON.parse(text);
  } catch {
    return JSON.parse(stripJsonComments(text).replace(/,\s*([}\]])/g, '$1'));
  }
}

function mergeArrays(base, patch) {
  const out = [...base];
  const seen = new Set(base.map((item) => JSON.stringify(item)));
  for (const item of patch) {
    const key = JSON.stringify(item);
    if (!seen.has(key)) {
      out.push(item);
      seen.add(key);
    }
  }
  return out;
}

export function mergeJson(base, patch) {
  if (Array.isArray(base) && Array.isArray(patch)) return mergeArrays(base, patch);
  if (base && typeof base === 'object' && patch && typeof patch === 'object') {
    const out = { ...base };
    for (const [key, value] of Object.entries(patch)) {
      if (!(key in out)) out[key] = value;
      else if (out[key] && typeof out[key] === 'object' && value && typeof value === 'object') {
        out[key] = mergeJson(out[key], value);
      }
    }
    return out;
  }
  return base;
}

export class Installer {
  constructor(options) {
    this.root = path.resolve(options.root);
    this.manifest = options.manifest;
    this.logger = options.logger;
    this.dryRun = Boolean(options.dryRun);
    this.force = Boolean(options.force);
    this.backupDir = path.join(this.root, '.aidf', 'backups', timestampSlug());
    this.results = [];
  }

  async backup(relPath, absPath) {
    if (this.dryRun) return;
    const target = resolveInside(this.backupDir, relPath);
    await ensureDir(path.dirname(target));
    await fsp.copyFile(absPath, target);
    this.manifest.backups.push({ path: relPath, backup: toPosix(path.relative(this.root, target)), ts: new Date().toISOString() });
    if (this.manifest.backups.length > 200) this.manifest.backups = this.manifest.backups.slice(-200);
  }

  push(result) {
    this.results.push(result);
    return result;
  }

  async apply(actions) {
    const start = this.results.length;
    for (const action of actions) {
      try {
        await this.applyOne(action);
      } catch (error) {
        this.push({
          path: action.path ?? action.blockId ?? '?',
          kind: action.kind,
          status: ACTION_STATUS.FAILED,
          reason: error.message,
        });
      }
    }
    return this.results.slice(start);
  }

  async applyOne(action) {
    switch (action.kind) {
      case 'mkdir':
        return this.mkdir(action);
      case 'write':
        return this.write(action);
      case 'copy':
        return this.copy(action);
      case 'managed-block':
        return this.managedBlock(action);
      case 'merge-json':
        return this.mergeJsonFile(action);
      case 'junction':
        return this.junction(action);
      default:
        throw new Error(`Unknown action kind: ${action.kind}`);
    }
  }

  async mkdir(action) {
    const abs = resolveInside(this.root, action.path);
    if (await exists(abs)) {
      return this.push({ path: action.path, kind: 'mkdir', status: ACTION_STATUS.UNCHANGED });
    }
    if (this.dryRun) {
      return this.push({ path: action.path, kind: 'mkdir', status: ACTION_STATUS.WOULD_CREATE });
    }
    await ensureDir(abs);
    return this.push({ path: action.path, kind: 'mkdir', status: ACTION_STATUS.CREATED });
  }

  async write(action) {
    const abs = resolveInside(this.root, action.path);
    const content = action.content ?? '';
    const nextHash = sha256Text(content);
    const record = fileRecord(this.manifest, action.path);
    const fileExists = await exists(abs);

    if (fileExists) {
      const currentHash = await sha256File(abs);
      // Frozen actions are user-owned content created once by AIDF (project
      // documents, editable registries, workflows). AIDF never rewrites them,
      // from install, update, or update --force: the human owns the content.
      if (action.frozen && currentHash !== nextHash) {
        return this.push({
          path: action.path,
          kind: 'write',
          status: ACTION_STATUS.UNCHANGED,
          reason: 'AIDF-owned template exists; user content is never rewritten (frozen).',
        });
      }
      if (currentHash === nextHash) {
        if (!record) {
          recordFile(this.manifest, action.path, {
            sha256: nextHash,
            category: action.category ?? 'unknown',
            owner: 'aidf',
            adopted: true,
          });
        }
        return this.push({ path: action.path, kind: 'write', status: ACTION_STATUS.UNCHANGED });
      }
      if (record && currentHash === record.sha256) {
        if (this.dryRun) return this.push({ path: action.path, kind: 'write', status: ACTION_STATUS.WOULD_UPDATE });
        await writeAtomic(abs, content);
        recordFile(this.manifest, action.path, {
          ...record,
          sha256: nextHash,
          category: action.category ?? record.category,
          updatedAt: new Date().toISOString(),
        });
        return this.push({ path: action.path, kind: 'write', status: ACTION_STATUS.UPDATED });
      }
      if (this.force) {
        if (this.dryRun) return this.push({ path: action.path, kind: 'write', status: ACTION_STATUS.WOULD_UPDATE, reason: 'force' });
        await this.backup(action.path, abs);
        await writeAtomic(abs, content);
        recordFile(this.manifest, action.path, {
          sha256: nextHash,
          category: action.category ?? 'unknown',
          owner: 'aidf',
          forced: true,
        });
        return this.push({ path: action.path, kind: 'write', status: ACTION_STATUS.UPDATED, reason: 'force' });
      }
      return this.push({
        path: action.path,
        kind: 'write',
        status: this.dryRun ? ACTION_STATUS.WOULD_CONFLICT : ACTION_STATUS.CONFLICT,
        reason: record
          ? 'File is AIDF-managed but was modified locally; use --force to overwrite (a backup is kept).'
          : 'File exists and is not managed by AIDF; it will not be overwritten.',
      });
    }

    if (this.dryRun) return this.push({ path: action.path, kind: 'write', status: ACTION_STATUS.WOULD_CREATE });
    await writeAtomic(abs, content);
    recordFile(this.manifest, action.path, {
      sha256: nextHash,
      category: action.category ?? 'unknown',
      owner: 'aidf',
    });
    return this.push({ path: action.path, kind: 'write', status: ACTION_STATUS.CREATED });
  }

  async copy(action) {
    const sourceAbs = path.resolve(action.source);
    const sourceContent = await fsp.readFile(sourceAbs);
    return this.write({ ...action, content: sourceContent.toString('utf8'), path: action.path });
  }

  async managedBlock(action) {
    const abs = resolveInside(this.root, action.path);
    const blockId = action.blockId ?? 'default';
    const fileExists = await exists(abs);
    const current = fileExists ? await readText(abs) : '';
    const next = replaceManagedBlock(current, action.content, blockId);
    if (next === current) {
      return this.push({ path: action.path, kind: 'managed-block', status: ACTION_STATUS.UNCHANGED });
    }
    if (this.dryRun) {
      return this.push({
        path: action.path,
        kind: 'managed-block',
        status: fileExists ? ACTION_STATUS.WOULD_UPDATE : ACTION_STATUS.WOULD_CREATE,
      });
    }
    if (fileExists) await this.backup(action.path, abs);
    await writeAtomic(abs, next);
    recordFile(this.manifest, action.path, {
      sha256: sha256Text(next),
      category: action.category ?? 'instructions',
      owner: 'aidf',
      managedBlock: blockId,
    });
    return this.push({
      path: action.path,
      kind: 'managed-block',
      status: fileExists ? ACTION_STATUS.UPDATED : ACTION_STATUS.CREATED,
    });
  }

  async mergeJsonFile(action) {
    const abs = resolveInside(this.root, action.path);
    const fileExists = await exists(abs);
    let current = {};
    if (fileExists) {
      const text = await readText(abs);
      try {
        current = parseJsonLoose(text);
      } catch (error) {
        return this.push({
          path: action.path,
          kind: 'merge-json',
          status: this.dryRun ? ACTION_STATUS.WOULD_CONFLICT : ACTION_STATUS.CONFLICT,
          reason: `Existing file is not valid JSON/JSONC: ${error.message}`,
        });
      }
      if (current === null || typeof current !== 'object' || Array.isArray(current)) {
        return this.push({
          path: action.path,
          kind: 'merge-json',
          status: this.dryRun ? ACTION_STATUS.WOULD_CONFLICT : ACTION_STATUS.CONFLICT,
          reason: 'Existing JSON root is not an object.',
        });
      }
    }
    const merged = mergeJson(current, action.patch ?? {});
    const nextText = `${JSON.stringify(merged, null, 2)}\n`;
    if (fileExists) {
      const currentText = await readText(abs);
      if (currentText === nextText) {
        return this.push({ path: action.path, kind: 'merge-json', status: ACTION_STATUS.UNCHANGED });
      }
    }
    if (this.dryRun) {
      return this.push({
        path: action.path,
        kind: 'merge-json',
        status: fileExists ? ACTION_STATUS.WOULD_UPDATE : ACTION_STATUS.WOULD_CREATE,
      });
    }
    if (fileExists) await this.backup(action.path, abs);
    await writeAtomic(abs, nextText);
    recordFile(this.manifest, action.path, {
      sha256: sha256Text(nextText),
      category: action.category ?? 'config',
      owner: 'aidf',
      mergeJson: true,
    });
    return this.push({
      path: action.path,
      kind: 'merge-json',
      status: fileExists ? ACTION_STATUS.UPDATED : ACTION_STATUS.CREATED,
    });
  }

  async junction(action) {
    const abs = resolveInside(this.root, action.path);
    const target = path.resolve(action.target);
    if (await exists(abs)) {
      try {
        const stat = await fsp.lstat(abs);
        if (stat.isSymbolicLink()) {
          const current = await fsp.readlink(abs);
          if (path.resolve(current) === target) {
            return this.push({ path: action.path, kind: 'junction', status: ACTION_STATUS.UNCHANGED });
          }
        }
      } catch {
        // fall through to conflict
      }
      return this.push({
        path: action.path,
        kind: 'junction',
        status: this.dryRun ? ACTION_STATUS.WOULD_CONFLICT : ACTION_STATUS.CONFLICT,
        reason: 'Path already exists and is not the expected link.',
      });
    }
    if (this.dryRun) return this.push({ path: action.path, kind: 'junction', status: ACTION_STATUS.WOULD_LINK });
    await ensureDir(path.dirname(abs));
    await fsp.symlink(target, abs, process.platform === 'win32' ? 'junction' : 'dir');
    this.manifest.links[action.path] = { target: toPosix(target), ts: new Date().toISOString() };
    return this.push({ path: action.path, kind: 'junction', status: ACTION_STATUS.LINKED });
  }
}

export function summarizeResults(results) {
  const summary = {
    created: 0, updated: 0, unchanged: 0, conflicts: 0, failed: 0, linked: 0, would: 0,
  };
  for (const r of results) {
    if (r.status === ACTION_STATUS.CREATED) summary.created += 1;
    else if (r.status === ACTION_STATUS.UPDATED) summary.updated += 1;
    else if (r.status === ACTION_STATUS.UNCHANGED) summary.unchanged += 1;
    else if (r.status === ACTION_STATUS.CONFLICT || r.status === ACTION_STATUS.WOULD_CONFLICT) summary.conflicts += 1;
    else if (r.status === ACTION_STATUS.FAILED) summary.failed += 1;
    else if (r.status === ACTION_STATUS.LINKED) summary.linked += 1;
    else if (r.status.startsWith('WOULD_')) summary.would += 1;
  }
  return summary;
}
