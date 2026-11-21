import path from 'node:path';
import { Adapter } from './base.mjs';
import { readText, exists } from '../../util/fsx.mjs';
import { loadSkillCatalog } from '../skills.mjs';

export class GenericAdapter extends Adapter {
  constructor() {
    super({
      key: 'generic',
      name: 'Generic (AGENTS.md)',
      capabilities: ['agents-md', 'skills'],
    });
  }

  async detect() {
    return {
      installed: true,
      capabilities: { agentsMd: true, rules: false, skills: true, hooks: false },
      limitations: ['No IDE-specific discovery is configured; the agent must read AGENTS.md, .aidf/AIDF.md and the skill registry.'],
    };
  }

  async plan(context) {
    const fragment = await readText(path.join(context.frameworkRoot, 'assets', 'core', 'AGENTS.fragment.md'));
    const actions = [{
      kind: 'managed-block',
      path: 'AGENTS.md',
      blockId: 'default',
      content: fragment,
      category: 'instructions',
    }];
    // Built-in skills are framework assets, not IDE-specific: install them
    // under .aidf/skills/ so any AGENTS.md-compatible agent can discover them
    // through .aidf/registry/skills.yaml.
    const catalog = await loadSkillCatalog(context.frameworkRoot);
    for (const skill of catalog) {
      actions.push({
        kind: 'write',
        path: path.posix.join('.aidf', 'skills', skill.name, 'SKILL.md'),
        content: await readText(path.join(context.frameworkRoot, 'assets', 'skills', skill.name, 'SKILL.md')),
        category: 'skills',
      });
    }
    return actions;
  }

  async verify(context) {
    const agents = path.join(context.root, 'AGENTS.md');
    const text = (await exists(agents)) ? await readText(agents) : '';
    const ok = text.includes('<!-- AIDF:BEGIN');
    const catalog = await loadSkillCatalog(context.frameworkRoot);
    let installed = 0;
    for (const skill of catalog) {
      if (await exists(path.join(context.root, '.aidf', 'skills', skill.name, 'SKILL.md'))) installed += 1;
    }
    return {
      status: ok && installed === catalog.length ? 'PROVEN' : 'FAIL',
      checks: [{
        id: 'agents-md-block',
        status: ok ? 'PASS' : 'FAIL',
        evidence: 'AGENTS.md managed block',
      }, {
        id: 'aidf-skills-installed',
        status: installed === catalog.length ? 'PASS' : 'FAIL',
        evidence: `${installed}/${catalog.length} built-in SKILL.md files under .aidf/skills/`,
      }],
      limitations: ['Skill availability means the files exist and validate; runtime loading depends on the agent reading the AIDF registry.'],
    };
  }
}
