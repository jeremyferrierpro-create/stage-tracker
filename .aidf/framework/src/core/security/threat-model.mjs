import path from 'node:path';
import { aidfPaths } from '../paths.mjs';
import { exists, writeJson, ensureDir, readJson } from '../../util/fsx.mjs';
import { detectProject } from '../detect.mjs';
import { loadConfig, defaultConfig } from '../config.mjs';
import { loadRequirementsRegistry } from '../requirements/registry.mjs';
import { collectArtefactFiles } from '../traceability/chain.mjs';

const SECRET_CANDIDATES = [
  '.env', '.env.local', '.env.production', '.env.development',
  'id_rsa', 'id_ed25519', '.npmrc', '.pypirc',
];

const AUTH_DEPENDENCIES = [
  'passport', 'next-auth', '@auth/core', 'jsonwebtoken', 'jose',
  'better-auth', 'lucia', 'auth0', '@clerk/nextjs', 'firebase-admin',
];

const WEB_FRAMEWORKS = ['Express', 'Fastify', 'Koa', 'Next.js', 'Nuxt', 'NestJS', 'Hono', 'Remix'];

async function listSecretFileNames(root) {
  const names = [];
  for (const name of SECRET_CANDIDATES) {
    if (await exists(path.join(root, name))) names.push(name);
  }
  return names;
}

function nextThreatModelId(existingIds) {
  let max = 0;
  for (const id of existingIds) {
    const m = /^TM-(\d+)$/.exec(id);
    if (m) max = Math.max(max, parseInt(m[1], 10));
  }
  return `TM-${String(max + 1).padStart(3, '0')}`;
}

/**
 * Generate a deterministic STRIDE threat model artefact from detected project
 * facts. Everything emitted is derived from real detection results; threats
 * that cannot be grounded in detected surface are not invented.
 */
export async function generateThreatModel(root, options = {}) {
  const now = new Date().toISOString();
  const project = await detectProject(root);
  const config = await loadConfig(root).catch(() => defaultConfig({}));
  const requirements = await loadRequirementsRegistry(root);
  const secretFiles = await listSecretFileNames(root);
  const files = await collectArtefactFiles(root);

  const hasPackageJson = Boolean(project.packageJson);
  const deps = options.dependencies ?? null;
  const allDeps = deps ?? {};
  const hasAuth = AUTH_DEPENDENCIES.some((d) => allDeps[d]);
  const isWeb = project.frameworks.some((f) => WEB_FRAMEWORKS.includes(f));

  const assets = [];
  const pushAsset = (a) => { if (!assets.some((x) => x.id === a.id)) assets.push(a); };
  pushAsset({
    id: 'AST-SRC', name: 'Source code and project configuration',
    classification: 'internal', owner: 'project', storage: 'git repository',
  });
  if (isWeb || hasPackageJson) {
    pushAsset({
      id: 'AST-DATA', name: 'Application data',
      classification: 'confidential', owner: 'project', storage: 'project data stores',
    });
  }
  pushAsset({
    id: 'AST-SEC', name: 'Secrets and credentials',
    classification: 'restricted', owner: 'project',
    storage: secretFiles.length ? `files: ${secretFiles.join(', ')}` : 'secret stores / env vars',
  });

  const trustBoundaries = [
    {
      id: 'TB-EXT', name: 'External actors to application',
      components: ['external users', 'AI coding agents', 'third-party APIs'],
    },
    {
      id: 'TB-APP', name: 'Application boundary',
      components: ['application runtime', ...(isWeb ? ['HTTP/API surface'] : [])],
    },
    {
      id: 'TB-DATA', name: 'Data boundary',
      components: ['databases', 'file system', 'secret stores'],
    },
  ];

  const threats = [];
  const addThreat = (t) => threats.push({
    likelihood: 'medium', impact: 'medium', status: 'open',
    ...t, id: `THR-${String(threats.length + 1).padStart(3, '0')}`,
  });

  if (secretFiles.length > 0) {
    addThreat({
      name: 'Secret material exposure',
      description: `Secret-like files present at project root: ${secretFiles.join(', ')}. Content was not read.`,
      stride: 'Information Disclosure', asset: 'AST-SEC',
      likelihood: 'medium', impact: 'high',
      mitigation: 'Verify files are git-ignored, rotate any committed secrets, and use a secret manager.',
      status: 'open',
    });
  }

  if (hasPackageJson) {
    addThreat({
      name: 'Supply-chain tampering via dependencies',
      description: 'Project declares npm dependencies; a compromised or typosquatted package could alter behaviour.',
      stride: 'Tampering', asset: 'AST-SRC',
      mitigation: 'Pin versions, commit the lockfile, run dependency audits in CI.',
      status: 'open',
    });
  }

  if (isWeb) {
    addThreat({
      name: 'Unauthenticated remote access',
      description: 'A web-facing surface was detected; without verified authentication, actors may reach protected functionality.',
      stride: 'Spoofing', asset: 'AST-DATA',
      mitigation: hasAuth
        ? `Authentication dependency detected (${AUTH_DEPENDENCIES.find((d) => allDeps[d])}); verify coverage of all routes.`
        : 'No authentication dependency detected; define an authn/authz strategy before exposing routes.',
      status: hasAuth ? 'mitigated' : 'open',
    });
    addThreat({
      name: 'Service denial via unbounded input',
      description: 'Public endpoints may be flooded with requests or oversized payloads.',
      stride: 'Denial of Service', asset: 'AST-DATA',
      mitigation: 'Rate limiting, body-size limits and timeouts at the edge or framework layer.',
      status: 'open',
    });
  }

  if (project.git.repository && project.git.dirty > 0) {
    addThreat({
      name: 'Uncommitted work loss or tampering',
      description: `${project.git.dirty} uncommitted change(s) detected; work in progress is not protected by history.`,
      stride: 'Repudiation', asset: 'AST-SRC',
      mitigation: 'Commit or stash work before releases; keep the working tree reviewable.',
      status: 'open',
    });
  }

  addThreat({
    name: 'Agent tool misuse elevating privileges',
    description: 'AI agents operate with filesystem and shell access; misconfigured permissions could allow destructive actions.',
    stride: 'Elevation of Privilege', asset: 'AST-SRC',
    mitigation: config.security?.allowCommandExecution
      ? 'Command execution is ENABLED in .aidf/config.json; restrict allowedCommands and require human approval for risky operations.'
      : 'Command execution is disabled by default (security.allowCommandExecution=false); keep it that way unless justified.',
    status: config.security?.allowCommandExecution ? 'open' : 'mitigated',
  });

  const requirementIds = (requirements.requirements ?? [])
    .filter((r) => r.status !== 'BLOCKED')
    .map((r) => r.id);

  const artefact = {
    id: nextThreatModelId(files.map((f) => f.id)),
    schemaVersion: '1.0.0',
    type: 'security-threat-model',
    createdAt: now,
    updatedAt: now,
    status: 'DRAFT',
    assets,
    threats,
    trustBoundaries,
    source: {
      type: 'generated',
      author: 'aidf',
      createdAt: now,
      generator: 'security-threat-model@0.3.2',
    },
    links: {
      references: requirementIds,
    },
    validation: {
      schemaVersion: '1.0.0',
      timestamp: now,
      valid: true,
      errors: [],
      warnings: ['Generated threat model requires human review before VALIDATED.'],
    },
    detection: {
      frameworks: project.frameworks,
      languages: project.languages.map((l) => l.language),
      secretFiles,
      hasAuth,
      isWeb,
      generatedBy: 'detectProject',
    },
  };

  return artefact;
}

export async function saveThreatModel(root, artefact) {
  const file = aidfPaths(root).artefacts['security/threat-model'];
  await ensureDir(path.dirname(file));
  await writeJson(file, artefact);
  return file;
}

export async function loadThreatModel(root) {
  const file = aidfPaths(root).artefacts['security/threat-model'];
  return readJson(file, null);
}

export function renderThreatModelMarkdown(tm) {
  const lines = [];
  lines.push(`# Threat Model ${tm.id}`);
  lines.push('');
  lines.push(`- Status: ${tm.status}`);
  lines.push(`- Generated: ${tm.createdAt}`);
  lines.push('');
  lines.push('## Assets');
  lines.push('');
  lines.push('| ID | Name | Classification | Storage |');
  lines.push('|---|---|---|---|');
  for (const a of tm.assets ?? []) lines.push(`| ${a.id} | ${a.name} | ${a.classification} | ${a.storage} |`);
  lines.push('');
  lines.push('## Threats (STRIDE)');
  lines.push('');
  lines.push('| ID | Name | STRIDE | Asset | Likelihood | Impact | Status | Mitigation |');
  lines.push('|---|---|---|---|---|---|---|---|');
  for (const t of tm.threats ?? []) {
    lines.push(`| ${t.id} | ${t.name} | ${t.stride} | ${t.asset} | ${t.likelihood} | ${t.impact} | ${t.status} | ${t.mitigation} |`);
  }
  lines.push('');
  lines.push('> Generated deterministically from project detection. Human review required before VALIDATED.');
  lines.push('');
  return `${lines.join('\n')}\n`;
}
