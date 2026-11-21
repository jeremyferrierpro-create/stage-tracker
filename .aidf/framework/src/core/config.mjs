import { aidfPaths, DOCUMENTS, DOC_STATUSES, AIDF_VERSION } from './paths.mjs';

import { readJson, writeJson, exists } from '../util/fsx.mjs';

export const CONFIG_SCHEMA_VERSION = 1;

export function defaultConfig(project = {}) {
  const documents = {};
  for (const doc of DOCUMENTS) {
    documents[doc] = {
      status: doc === 'constitution' ? 'DRAFT' : 'DRAFT',
      validatedBy: null,
      validatedAt: null,
      note: 'Generated at installation. Must be completed from real project analysis.',
    };
  }
  return {
    schemaVersion: CONFIG_SCHEMA_VERSION,
    project: {
      name: project.name ?? 'unnamed-project',
      slug: project.slug ?? 'unnamed-project',
      description: project.description ?? '',
    },
    framework: {
      name: 'aidf',
      version: AIDF_VERSION,
      channel: 'local',
    },
    adapter: project.adapter ?? 'generic',
    commands: project.commands ?? {
      test: null,
      build: null,
      lint: null,
      securityAudit: null,
    },
    documents,
    workflow: {
      currentPhase: 'intake',
      history: [],
    },
    skills: {
      roots: [],
      maxActive: 5,
    },
    context: {
      maxDocuments: 8,
      maxSkills: 5,
      alwaysInclude: ['constitution', 'CONVENTIONS'],
    },
    security: {
      allowCommandExecution: false,
      allowedCommands: [],
      allowNetwork: false,
      allowedHosts: [],
      protectSecrets: true,
      requireHumanApprovalFor: ['security', 'release'],
      // Declared approvals (--human --by) are recorded but not authenticated.
      // Set to true to make gates reject declared-only approvals.
      requireAuthenticatedApproval: false,
    },
    specKit: {
      enabled: 'auto',
      integration: 'qodercli',
      constitutionSync: 'copy-with-hash',
    },
    evidence: {
      directory: '.aidf/evidence',
      retentionDays: 365,
    },
    createdAt: new Date().toISOString(),
    updatedAt: new Date().toISOString(),
  };
}

export async function loadConfig(root) {
  const paths = aidfPaths(root);
  const config = await readJson(paths.config, null);
  if (!config) throw new Error(`AIDF is not initialized in ${root} (missing .aidf/config.json).`);
  return config;
}

export async function saveConfig(root, config) {
  const paths = aidfPaths(root);
  config.updatedAt = new Date().toISOString();
  await writeJson(paths.config, config);
}

export async function configExists(root) {
  return exists(aidfPaths(root).config);
}

export function validateConfig(config) {
  const errors = [];
  if (!config || typeof config !== 'object') return ['config must be an object'];
  if (config.schemaVersion !== CONFIG_SCHEMA_VERSION) errors.push(`unsupported schemaVersion: ${config.schemaVersion}`);
  if (!config.project || typeof config.project.name !== 'string') errors.push('project.name is required');
  if (!config.documents || typeof config.documents !== 'object') errors.push('documents is required');
  for (const doc of DOCUMENTS) {
    const entry = config.documents?.[doc];
    if (!entry) errors.push(`missing document entry: ${doc}`);
    else if (!DOC_STATUSES.includes(entry.status)) errors.push(`invalid status for ${doc}: ${entry.status}`);
  }
  if (!Array.isArray(config.security?.allowedCommands)) errors.push('security.allowedCommands must be an array');
  if (config.security?.requireAuthenticatedApproval !== undefined
    && typeof config.security.requireAuthenticatedApproval !== 'boolean') {
    errors.push('security.requireAuthenticatedApproval must be a boolean');
  }
  return errors;
}

export function setDocumentStatus(config, doc, status, meta = {}) {
  if (!DOCUMENTS.includes(doc)) throw new Error(`Unknown document: ${doc}`);
  if (!DOC_STATUSES.includes(status)) throw new Error(`Invalid document status: ${status}`);
  const current = config.documents[doc] ?? {};
  config.documents[doc] = {
    ...current,
    status,
    validatedBy: meta.by ?? current.validatedBy ?? null,
    validatedAt: meta.at ?? (status === 'VALIDATED' ? new Date().toISOString() : null),
    note: meta.note ?? current.note ?? '',
  };
  return config.documents[doc];
}
