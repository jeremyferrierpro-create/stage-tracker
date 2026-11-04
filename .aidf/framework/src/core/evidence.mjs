import path from 'node:path';
import { aidfPaths } from './paths.mjs';
import {
  readJson, writeJson, readDirSafe, readText, sha256Text, exists, timestampSlug,
} from '../util/fsx.mjs';
import { runTool } from './detect.mjs';

export const EVIDENCE_STATUSES = ['PASS', 'BLOCKED', 'FAIL', 'NOT_PROVEN', 'NOT_APPLICABLE'];

export async function loadEvidenceIndex(root) {
  const file = path.join(aidfPaths(root).evidence, 'index.json');
  return (await readJson(file, null)) ?? { schemaVersion: 1, updatedAt: null, lastHash: null, entries: [] };
}

export async function saveEvidenceIndex(root, index) {
  index.updatedAt = new Date().toISOString();
  await writeJson(path.join(aidfPaths(root).evidence, 'index.json'), index);
}

export async function recordEvidence(root, entry) {
  const index = await loadEvidenceIndex(root);
  const createdAt = new Date().toISOString();
  const body = {
    schemaVersion: 1,
    id: entry.id ?? `ev-${timestampSlug()}-${Math.random().toString(16).slice(2, 8)}`,
    type: entry.type,
    status: entry.status ?? 'NOT_PROVEN',
    gate: entry.gate ?? null,
    phase: entry.phase ?? null,
    summary: entry.summary ?? '',
    note: entry.note ?? '',
    createdBy: entry.createdBy ?? 'aidf',
    humanApproval: Boolean(entry.humanApproval),
    approvedBy: entry.approvedBy ?? null,
    createdAt,
    checks: entry.checks ?? [],
    commands: entry.commands ?? [],
    files: entry.files ?? [],
    limitations: entry.limitations ?? [],
    previous: index.lastHash,
  };
  const hash = sha256Text(JSON.stringify(body));
  body.hash = hash;
  const file = path.join(aidfPaths(root).evidence, `${timestampSlug()}-${entry.type}-${hash.slice(0, 8)}.json`);
  await writeJson(file, body);
  index.entries.push({
    id: body.id,
    type: body.type,
    status: body.status,
    gate: body.gate,
    phase: body.phase,
    createdAt,
    humanApproval: body.humanApproval,
    approvedBy: body.approvedBy,
    file: path.relative(root, file).replace(/\\/g, '/'),
    hash,
  });
  index.lastHash = hash;
  await saveEvidenceIndex(root, index);
  return { ...body, file: path.relative(root, file).replace(/\\/g, '/') };
}

export async function listEvidence(root, filter = {}) {
  const index = await loadEvidenceIndex(root);
  return index.entries
    .filter((e) => !filter.type || e.type === filter.type)
    .filter((e) => !filter.status || e.status === filter.status)
    .filter((e) => !filter.gate || e.gate === filter.gate)
    .sort((a, b) => b.createdAt.localeCompare(a.createdAt));
}

export async function findEvidence(root, filter = {}) {
  return listEvidence(root, filter);
}

export async function captureFiles(root, options = {}) {
  const git = await runTool('git', ['rev-parse', '--is-inside-work-tree'], { cwd: root });
  if (!git.ok || git.stdout !== 'true') return { files: [], source: 'not-a-git-repository' };
  const args = ['diff', '--name-only'];
  if (options.staged) args.push('--cached');
  if (options.since) args.push(options.since);
  const diff = await runTool('git', args, { cwd: root });
  const untracked = await runTool('git', ['ls-files', '--others', '--exclude-standard'], { cwd: root });
  const files = [...new Set([
    ...(diff.ok ? diff.stdout.split(/\r?\n/).filter(Boolean) : []),
    ...(untracked.ok ? untracked.stdout.split(/\r?\n/).filter(Boolean) : []),
  ])];
  return { files, source: 'git' };
}

export async function verifyEvidenceFile(root, entry) {
  if (!entry.file) return { ok: false, reason: 'No file recorded.' };
  const file = path.join(root, entry.file);
  if (!(await exists(file))) return { ok: false, reason: 'Evidence file is missing.' };
  const body = JSON.parse(await readText(file));
  const copy = { ...body };
  delete copy.hash;
  const expected = sha256Text(JSON.stringify(copy));
  if (expected !== body.hash) return { ok: false, reason: 'Evidence hash does not match content.' };
  return { ok: true };
}

export async function listEvidenceFiles(root) {
  const dir = aidfPaths(root).evidence;
  const entries = await readDirSafe(dir);
  return entries.filter((e) => e.isFile() && e.name.endsWith('.json')).map((e) => e.name);
}
