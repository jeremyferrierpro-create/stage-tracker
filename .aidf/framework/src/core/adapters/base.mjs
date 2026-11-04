import path from 'node:path';
import { exists } from '../../util/fsx.mjs';

export class Adapter {
  constructor(options = {}) {
    this.key = options.key ?? 'generic';
    this.name = options.name ?? 'Generic';
    this.capabilities = options.capabilities ?? [];
  }

  async detect() {
    return { installed: true, capabilities: this.capabilities };
  }

  async plan() {
    return [];
  }

  async verify() {
    return { status: 'NOT_PROVEN', checks: [] };
  }
}

export async function readIfExists(file) {
  return (await exists(file)) ? file : null;
}

export function skillTargetPath(adapterKey, skillName) {
  if (adapterKey === 'qoder') return path.posix.join('.qoder', 'skills', skillName, 'SKILL.md');
  return path.posix.join('.aidf', 'skills', skillName, 'SKILL.md');
}
