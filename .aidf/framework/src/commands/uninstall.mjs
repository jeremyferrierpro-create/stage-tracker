import path from 'node:path';
import fsp from 'node:fs/promises';
import { parseArgs } from '../util/args.mjs';
import { resolveProjectRoot, isInitialized, aidfPaths } from '../core/paths.mjs';
import { loadManifest } from '../core/manifest.mjs';
import { removeManagedBlock } from '../util/text.mjs';
import {
  exists, readText, writeAtomic, sha256File, removeFile, removeDir, ensureDir, timestampSlug, toPosix,
} from '../util/fsx.mjs';
import { attachLogFile } from '../util/logger.mjs';

export function uninstallArgSpec() {
  return {
    booleans: ['dry-run', 'yes', 'force', 'keep-data', 'json', 'help'],
    aliases: { h: 'help', t: 'target' },
  };
}

const AIDF_OWNED_DIRS = [
  'framework', 'bin', 'registry', 'workflows', 'state', 'logs', 'cache',
  'backups', 'hooks', 'adapters', 'knowledge', 'reports', 'skills',
];

async function removeEmptyParents(root, relPath) {
  let current = path.dirname(path.join(root, relPath));
  const stop = path.resolve(root);
  while (current.startsWith(stop) && current !== stop) {
    try {
      const entries = await fsp.readdir(current);
      if (entries.length > 0) break;
      await fsp.rmdir(current);
    } catch {
      break;
    }
    current = path.dirname(current);
  }
}

/**
 * Remove AIDF-managed artefacts. Files whose content no longer matches the
 * manifest hash are treated as project-owned changes and preserved unless
 * --force is passed (in which case they are copied to .aidf-uninstall-backup).
 */
export async function uninstallCommand(argv, ctx) {
  const args = parseArgs(argv, uninstallArgSpec());
  if (args.help) {
    ctx.logger.info('Usage: aidf uninstall [--target <dir>] [--dry-run] [--yes] [--keep-data] [--force]');
    return 0;
  }
  const root = resolveProjectRoot({ target: args.target, cwd: process.cwd() });
  if (!isInitialized(root)) {
    ctx.logger.info(`AIDF is not installed in ${root}; nothing to uninstall.`);
    return 0;
  }
  await attachLogFile(ctx.logger, root).catch(() => null);
  const manifest = await loadManifest(root);
  const dryRun = Boolean(args['dry-run']);
  const force = Boolean(args.force);
  const keepData = Boolean(args['keep-data']);
  if (!dryRun && !args.yes) {
    ctx.logger.error('Refusing to uninstall without --yes. Use --dry-run to preview the removals.');
    return 1;
  }
  // A dry-run preview must not write anything, including logs.
  if (!dryRun) {
    await attachLogFile(ctx.logger, root).catch(() => null);
  }

  const results = [];
  const backupDir = path.join(root, '.aidf-uninstall-backup', timestampSlug());

  async function backup(relPath, absPath) {
    const target = path.join(backupDir, relPath);
    await ensureDir(path.dirname(target));
    await fsp.copyFile(absPath, target);
    return toPosix(path.relative(root, target));
  }

  // 1. Files outside .aidf/ are only removed when their hash still matches.
  // Managed blocks and merged JSON are handled separately so user content and
  // unrelated settings survive.
  const externalFiles = Object.keys(manifest.files).filter((rel) => {
    if (rel.startsWith('.aidf/')) return false;
    const record = manifest.files[rel];
    return !record?.managedBlock && !record?.mergeJson;
  });
  for (const rel of externalFiles) {
    const abs = path.join(root, rel);
    if (!(await exists(abs))) {
      results.push({ path: rel, status: 'ABSENT' });
      continue;
    }
    const record = manifest.files[rel];
    const currentHash = await sha256File(abs);
    const unmodified = record?.sha256 ? currentHash === record.sha256 : false;
    if (!unmodified && !force) {
      results.push({
        path: rel,
        status: 'CONFLICT',
        reason: 'File was modified after installation; preserved. Use --force to remove it (a backup is kept).',
      });
      continue;
    }
    if (dryRun) {
      results.push({ path: rel, status: 'WOULD_REMOVE' });
      continue;
    }
    if (!unmodified && force) {
      const backupPath = await backup(rel, abs);
      await removeFile(abs);
      results.push({ path: rel, status: 'REMOVED', backup: backupPath });
    } else {
      await removeFile(abs);
      results.push({ path: rel, status: 'REMOVED' });
    }
    await removeEmptyParents(root, rel);
  }

  // 2. Links created by AIDF.
  for (const rel of Object.keys(manifest.links ?? {})) {
    const abs = path.join(root, rel);
    if (!(await exists(abs))) {
      results.push({ path: rel, status: 'ABSENT', kind: 'link' });
      continue;
    }
    if (dryRun) {
      results.push({ path: rel, status: 'WOULD_REMOVE', kind: 'link' });
      continue;
    }
    try {
      const stat = await fsp.lstat(abs);
      if (stat.isSymbolicLink()) await fsp.unlink(abs);
      else await removeDir(abs);
      results.push({ path: rel, status: 'REMOVED', kind: 'link' });
    } catch (error) {
      results.push({ path: rel, status: 'FAILED', kind: 'link', reason: error.message });
    }
  }

  // 3. Remove the managed AGENTS.md block, preserving user content.
  const agentsRel = 'AGENTS.md';
  const agentsAbs = path.join(root, agentsRel);
  if (await exists(agentsAbs)) {
    const current = await readText(agentsAbs);
    const next = removeManagedBlock(current, 'default');
    if (next !== current) {
      if (dryRun) {
        results.push({ path: agentsRel, status: 'WOULD_UPDATE', kind: 'managed-block' });
      } else if (next.trim() === '') {
        await removeFile(agentsAbs);
        results.push({ path: agentsRel, status: 'REMOVED', kind: 'managed-block' });
      } else {
        await writeAtomic(agentsAbs, next);
        results.push({ path: agentsRel, status: 'UPDATED', kind: 'managed-block' });
      }
    }
  }

  // 4. Remove AIDF hook entries from .qoder/settings.json without touching others.
  const settingsAbs = path.join(root, '.qoder', 'settings.json');
  if (await exists(settingsAbs)) {
    let settings = null;
    try {
      settings = JSON.parse(await readText(settingsAbs));
    } catch {
      results.push({ path: '.qoder/settings.json', status: 'CONFLICT', reason: 'Not valid JSON; left untouched.' });
    }
    if (settings && settings.hooks) {
      let changed = false;
      for (const [event, groups] of Object.entries(settings.hooks)) {
        if (!Array.isArray(groups)) continue;
        const kept = groups
          .map((group) => {
            if (!group || !Array.isArray(group.hooks)) return group;
            const hooks = group.hooks.filter((hook) => !String(hook?.command ?? '').includes('.aidf/hooks/'));
            if (hooks.length !== group.hooks.length) changed = true;
            return hooks.length === 0 ? null : { ...group, hooks };
          })
          .filter(Boolean);
        if (kept.length === 0) {
          delete settings.hooks[event];
          changed = true;
        } else {
          settings.hooks[event] = kept;
        }
      }
      if (Object.keys(settings.hooks).length === 0) delete settings.hooks;
      if (changed) {
        if (dryRun) {
          results.push({ path: '.qoder/settings.json', status: 'WOULD_UPDATE', kind: 'hooks' });
        } else {
          await writeAtomic(settingsAbs, `${JSON.stringify(settings, null, 2)}\n`);
          results.push({ path: '.qoder/settings.json', status: 'UPDATED', kind: 'hooks' });
        }
      }
    }
  }

  // 5. Remove the AIDF data directory (or keep docs/evidence with --keep-data).
  const paths = aidfPaths(root);
  if (dryRun) {
    results.push({ path: '.aidf', status: keepData ? 'WOULD_UPDATE' : 'WOULD_REMOVE', kind: 'directory' });
  } else if (keepData) {
    for (const dir of AIDF_OWNED_DIRS) {
      await removeDir(path.join(paths.base, dir));
    }
    for (const file of ['config.json', 'manifest.json', '.gitignore', 'AIDF.md']) {
      await removeFile(path.join(paths.base, file));
    }
    results.push({
      path: '.aidf',
      status: 'UPDATED',
      kind: 'directory',
      reason: 'Kept .aidf/docs and .aidf/evidence; removed framework and state.',
    });
  } else {
    await removeDir(paths.base);
    results.push({ path: '.aidf', status: 'REMOVED', kind: 'directory' });
  }

  const conflicts = results.filter((r) => r.status === 'CONFLICT');
  if (ctx.json) {
    process.stdout.write(`${JSON.stringify({ root, dryRun, results }, null, 2)}\n`);
  } else {
    ctx.logger.info(`${dryRun ? '[dry-run] ' : ''}AIDF uninstall — ${root}`);
    for (const result of results) {
      const suffix = result.reason ? ` — ${result.reason}` : result.backup ? ` — backup: ${result.backup}` : '';
      ctx.logger.info(`${result.status} ${result.path}${suffix}`);
    }
    if (conflicts.length > 0) {
      ctx.logger.warn(`${conflicts.length} file(s) preserved because they were modified after installation.`);
    }
    if (backupDir && (await exists(backupDir))) {
      ctx.logger.info(`Backups: ${toPosix(path.relative(root, backupDir))}`);
    }
  }
  return conflicts.length > 0 ? 2 : 0;
}
