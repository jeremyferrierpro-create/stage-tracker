import path from 'node:path';
import { Adapter } from './base.mjs';
import { readText, exists } from '../../util/fsx.mjs';
import { loadSkillCatalog } from '../skills.mjs';

/**
 * OpenCode adapter.
 *
 * Verified against https://opencode.ai/docs/skills/: OpenCode discovers
 * skills in `.opencode/skills/<id>/SKILL.md` (project scope) and reads
 * AGENTS.md as the instruction file. This adapter writes exactly those two
 * surfaces and does not claim runtime activation: only a real OpenCode session
 * can prove that a skill is loaded.
 */
export class OpenCodeAdapter extends Adapter {
  constructor() {
    super({
      key: 'opencode',
      name: 'OpenCode',
      capabilities: ['agents-md', 'skills'],
    });
  }

  async detect(env) {
    return {
      installed: Boolean(env?.ide?.opencode?.installed),
      home: env?.ide?.opencode?.home ?? null,
      capabilities: {
        agentsMd: true,
        rules: false,
        skills: true,
        hooks: false,
        customAgents: false,
        mcp: false,
        plugins: false,
      },
      limitations: [
        'OpenCode skill discovery is file-based; AIDF can write .opencode/skills/<id>/SKILL.md but cannot prove the running session advertised it.',
        'AGENTS.md is loaded as instructions; runtime compliance is not observable from the filesystem.',
        'Verified 2026-11-21: OpenCode only discovers project skills when the project root is a git worktree (a .git entry). Without it, .opencode/skills/* is silently ignored.',
      ],
    };
  }

  async plan(context) {
    const { frameworkRoot } = context;
    const actions = [];
    const fragment = await readText(path.join(frameworkRoot, 'assets', 'core', 'AGENTS.fragment.md'));
    actions.push({
      kind: 'managed-block',
      path: 'AGENTS.md',
      blockId: 'default',
      content: fragment,
      category: 'instructions',
    });

    const catalog = await loadSkillCatalog(frameworkRoot);
    for (const skill of catalog) {
      actions.push({
        kind: 'write',
        path: path.posix.join('.opencode', 'skills', skill.name, 'SKILL.md'),
        content: await readText(path.join(frameworkRoot, 'assets', 'skills', skill.name, 'SKILL.md')),
        category: 'skills',
      });
    }
    return actions;
  }

  async verify(context) {
    const { root, frameworkRoot } = context;
    const checks = [];
    const agents = path.join(root, 'AGENTS.md');
    const agentsText = (await exists(agents)) ? await readText(agents) : '';
    const agentsOk = agentsText.includes('<!-- AIDF:BEGIN');
    checks.push({
      id: 'agents-md-block',
      status: agentsOk ? 'PASS' : 'FAIL',
      evidence: 'AGENTS.md managed block present',
    });

    const catalog = await loadSkillCatalog(frameworkRoot);
    let installed = 0;
    for (const skill of catalog) {
      if (await exists(path.join(root, '.opencode', 'skills', skill.name, 'SKILL.md'))) installed += 1;
    }
    checks.push({
      id: 'skill-files',
      status: installed === catalog.length ? 'PASS' : installed === 0 ? 'FAIL' : 'PARTIAL',
      evidence: `${installed}/${catalog.length} built-in SKILL.md files present`,
    });
    const isGitWorktree = await exists(path.join(root, '.git'));
    checks.push({
      id: 'git-worktree',
      status: isGitWorktree ? 'PASS' : 'PARTIAL',
      evidence: isGitWorktree
        ? 'Project is a git worktree; OpenCode can discover project skills.'
        : 'No .git entry: OpenCode ignores .opencode/skills/* outside a git worktree (verified 2026-11-21). Run git init or use another adapter.',
    });
    checks.push({
      id: 'runtime-skill-discovery',
      status: 'NOT_PROVEN',
      evidence: 'Requires a real OpenCode session that lists or invokes an AIDF skill.',
    });

    const failed = checks.some((c) => c.status === 'FAIL');
    const partial = checks.some((c) => c.status === 'PARTIAL');
    return {
      status: failed ? 'FAIL' : partial ? 'PARTIAL' : 'CONTRACT-PROVEN',
      checks,
      limitations: [
        'Runtime discovery is NOT_PROVEN until an OpenCode session invokes an AIDF skill.',
      ],
    };
  }
}
