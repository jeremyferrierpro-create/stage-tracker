import path from 'node:path';
import { Adapter } from './base.mjs';
import { readText, exists } from '../../util/fsx.mjs';

export class GenericAdapter extends Adapter {
  constructor() {
    super({
      key: 'generic',
      name: 'Generic (AGENTS.md)',
      capabilities: ['agents-md'],
    });
  }

  async detect() {
    return {
      installed: true,
      capabilities: { agentsMd: true, rules: false, skills: false, hooks: false },
      limitations: ['No IDE-specific discovery is configured; the agent must read AGENTS.md and .aidf/AIDF.md.'],
    };
  }

  async plan(context) {
    const fragment = await readText(path.join(context.frameworkRoot, 'assets', 'core', 'AGENTS.fragment.md'));
    return [{
      kind: 'managed-block',
      path: 'AGENTS.md',
      blockId: 'default',
      content: fragment,
      category: 'instructions',
    }];
  }

  async verify(context) {
    const agents = path.join(context.root, 'AGENTS.md');
    const text = (await exists(agents)) ? await readText(agents) : '';
    const ok = text.includes('<!-- AIDF:BEGIN');
    return {
      status: ok ? 'PROVEN' : 'FAIL',
      checks: [{
        id: 'agents-md-block',
        status: ok ? 'PASS' : 'FAIL',
        evidence: 'AGENTS.md managed block',
      }],
      limitations: ['Generic adapter does not configure IDE-specific skills, rules or hooks.'],
    };
  }
}
