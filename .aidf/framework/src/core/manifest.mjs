import { aidfPaths, AIDF_VERSION } from './paths.mjs';
import { readJson, writeJson } from '../util/fsx.mjs';

export const MANIFEST_SCHEMA_VERSION = 1;

export function emptyManifest(project = {}) {
  return {
    schemaVersion: MANIFEST_SCHEMA_VERSION,
    framework: {
      name: 'aidf',
      version: AIDF_VERSION,
    },
    project: {
      name: project.name ?? 'unnamed-project',
      slug: project.slug ?? 'unnamed-project',
    },
    adapter: project.adapter ?? 'generic',
    installedAt: new Date().toISOString(),
    updatedAt: new Date().toISOString(),
    files: {},
    managedBlocks: {},
    links: {},
    backups: [],
    history: [],
  };
}

export async function loadManifest(root) {
  const manifest = await readJson(aidfPaths(root).manifest, null);
  if (!manifest) throw new Error(`AIDF manifest not found in ${root}.`);
  return manifest;
}

export async function saveManifest(root, manifest) {
  manifest.updatedAt = new Date().toISOString();
  await writeJson(aidfPaths(root).manifest, manifest);
}

export function recordFile(manifest, relPath, entry) {
  manifest.files[relPath] = {
    ...entry,
    installedAt: entry.installedAt ?? new Date().toISOString(),
  };
}

export function fileRecord(manifest, relPath) {
  return manifest.files[relPath] ?? null;
}

export function appendHistory(manifest, event) {
  manifest.history.push({ ts: new Date().toISOString(), ...event });
  if (manifest.history.length > 500) manifest.history = manifest.history.slice(-500);
}
