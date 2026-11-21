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
    // `approvedBy` is kept for historical compatibility. It is a *declared*
    // identity only; authentication is never inferred from it.
    approval: entry.approval ?? {
      declaredBy: entry.humanApproval ? (entry.approvedBy ?? null) : null,
      declaredAt: entry.humanApproval ? createdAt : null,
      authenticated: false,
      method: entry.humanApproval ? 'declared' : 'none',
      keyId: null,
    },
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
    authenticatedApproval: Boolean(body.approval?.authenticated),
    approvalMethod: body.approval?.method ?? null,
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

/**
 * Verify the whole evidence chain: every record hash, every `previous` link,
 * chronological order, index consistency and unindexed/missing files.
 * Never repairs or rewrites history: inconsistencies are reported.
 */
export async function verifyEvidenceChain(root) {
  const problems = [];
  const add = (code, file, reason) => problems.push({ code, file: file ?? null, reason });
  const index = await loadEvidenceIndex(root);
  if (!Array.isArray(index.entries)) {
    add('INVALID_INDEX', null, 'index.entries is not an array.');
    return { ok: false, checked: 0, problems };
  }
  const entries = index.entries;
  const seenIds = new Set();
  const seenFiles = new Set();
  let previous = null;
  let previousAt = null;

  for (const entry of entries) {
    if (!entry || typeof entry !== 'object' || !entry.file || !entry.id) {
      add('INVALID_ENTRY', entry?.file ?? null, 'Index entry requires an id and a file.');
      previous = entry?.hash ?? previous;
      continue;
    }
    if (seenIds.has(entry.id)) add('DUPLICATE_ID', entry.file, `Duplicate evidence id: ${entry.id}.`);
    seenIds.add(entry.id);
    if (seenFiles.has(entry.file)) add('DUPLICATE_FILE', entry.file, 'The same evidence file is indexed more than once.');
    seenFiles.add(entry.file);

    const abs = path.join(root, entry.file);
    if (!(await exists(abs))) {
      add('MISSING_FILE', entry.file, 'Evidence file is missing.');
      previous = entry.hash ?? previous;
      previousAt = entry.createdAt ?? previousAt;
      continue;
    }

    let body;
    try {
      body = JSON.parse(await readText(abs));
    } catch (error) {
      add('INVALID_JSON', entry.file, `Evidence file is not valid JSON: ${error.message}`);
      previous = entry.hash ?? previous;
      previousAt = entry.createdAt ?? previousAt;
      continue;
    }

    const copy = { ...body };
    delete copy.hash;
    if (sha256Text(JSON.stringify(copy)) !== body.hash) {
      add('HASH_MISMATCH', entry.file, 'Record content does not match its stored hash.');
    }
    if (entry.hash !== body.hash) add('INDEX_HASH_MISMATCH', entry.file, 'Index hash differs from record hash.');
    if (entry.id !== body.id) add('INDEX_ID_MISMATCH', entry.file, 'Index id differs from record id.');
    if (entry.status !== undefined && entry.status !== body.status) {
      add('INDEX_STATUS_MISMATCH', entry.file, 'Index status differs from record status.');
    }
    if ((body.previous ?? null) !== (previous ?? null)) {
      add('CHAIN_BROKEN', entry.file, `Expected previous ${previous ?? 'null'}, found ${body.previous ?? 'null'}.`);
    }
    if (previousAt && entry.createdAt && String(entry.createdAt) < String(previousAt)) {
      add('ORDER', entry.file, 'Entry is older than its predecessor in the chain.');
    }
    previous = body.hash;
    previousAt = entry.createdAt ?? previousAt;
  }

  if (entries.length > 0 && index.lastHash !== previous) {
    add('INDEX_LAST_HASH_MISMATCH', null, 'index.lastHash does not match the last record hash.');
  }
  if (entries.length === 0 && index.lastHash) {
    add('INDEX_LAST_HASH_MISMATCH', null, 'index.lastHash is set but the index contains no entries.');
  }

  const dir = aidfPaths(root).evidence;
  let files = [];
  try {
    files = (await readDirSafe(dir))
      .filter((e) => e.isFile() && e.name.endsWith('.json') && e.name !== 'index.json')
      .map((e) => path.relative(root, path.join(dir, e.name)).replace(/\\/g, '/'));
  } catch {
    files = [];
  }
  const referenced = new Set([...seenFiles].map((f) => String(f).replace(/\\/g, '/')));
  for (const file of files) {
    if (!referenced.has(file)) add('UNINDEXED_RECORD', file, 'Record file is not referenced by the index.');
  }

  return { ok: problems.length === 0, checked: entries.length, problems };
}

export async function listEvidenceFiles(root) {
  const dir = aidfPaths(root).evidence;
  const entries = await readDirSafe(dir);
  return entries.filter((e) => e.isFile() && e.name.endsWith('.json')).map((e) => e.name);
}
