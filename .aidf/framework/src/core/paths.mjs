import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { existsSync } from '../util/fsx.mjs';

export const FRAMEWORK_ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..', '..');
export const AIDF_DIR = '.aidf';
export const AIDF_VERSION = '0.1.0';

export const DOCUMENTS = [
  'PRD',
  'TRD',
  'APPFLOW',
  'UI-UX',
  'BACKEND',
  'PLAN',
  'SECURITY',
  'CONVENTIONS',
  'constitution',
  'DECISIONS',
  'CHANGELOG',
];

export const DOC_STATUSES = ['DRAFT', 'VALIDATED', 'BLOCKED', 'OUTDATED', 'NOT_APPLICABLE'];

export const PHASES = [
  'intake',
  'discovery',
  'specification',
  'planning',
  'implementation',
  'testing',
  'security',
  'release',
];

export function aidfPaths(root) {
  const base = path.join(root, AIDF_DIR);
  return {
    root,
    base,
    config: path.join(base, 'config.json'),
    manifest: path.join(base, 'manifest.json'),
    docs: path.join(base, 'docs'),
    registry: path.join(base, 'registry'),
    workflows: path.join(base, 'workflows'),
    evidence: path.join(base, 'evidence'),
    state: path.join(base, 'state'),
    logs: path.join(base, 'logs'),
    cache: path.join(base, 'cache'),
    skills: path.join(base, 'skills'),
    projectSkills: path.join(base, 'skills', 'project-skills'),
    knowledge: path.join(base, 'knowledge'),
    backups: path.join(base, 'backups'),
    hooks: path.join(base, 'hooks'),
    bin: path.join(base, 'bin'),
    adapters: path.join(base, 'adapters'),
    context: path.join(base, 'state', 'context'),
    reports: path.join(base, 'reports'),
  };
}

export function findProjectRoot(startDir) {
  let current = path.resolve(startDir);
  for (;;) {
    if (existsSync(path.join(current, AIDF_DIR, 'manifest.json'))) return current;
    const parent = path.dirname(current);
    if (parent === current) return null;
    current = parent;
  }
}

export function resolveProjectRoot(options = {}) {
  if (options.target) return path.resolve(options.target);
  const found = findProjectRoot(options.cwd ?? process.cwd());
  if (found) return found;
  return path.resolve(options.cwd ?? process.cwd());
}

export function isInitialized(root) {
  return existsSync(path.join(root, AIDF_DIR, 'manifest.json'));
}
