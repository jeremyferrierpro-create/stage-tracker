import path from 'node:path';
import { Adapter } from './base.mjs';
import { readText, exists } from '../../util/fsx.mjs';
import { validateSkillContent, loadSkillCatalog } from '../skills.mjs';

export class QoderAdapter extends Adapter {
  constructor() {
    super({
      key: 'qoder',
      name: 'Qoder IDE',
      capabilities: ['agents-md', 'rules', 'skills', 'hooks', 'custom-agents'],
    });
  }

  async detect(env) {
    return {
      installed: Boolean(env?.ide?.qoder?.installed),
      home: env?.ide?.qoder?.home ?? null,
      capabilities: {
        agentsMd: true,
        rules: true,
        skills: true,
        hooks: true,
        customAgents: true,
        mcp: true,
        plugins: true,
      },
      limitations: [
        'Rule activation type (Always Apply / Model Decision / Specific Files / Apply Manually) is set in the Qoder UI; AIDF can create rule files but cannot prove their activation from the filesystem.',
        'Skill and rule runtime discovery must be confirmed in a Qoder session (e.g. /skills).',
        'Qoder IDE documentation and Qoder CLI documentation differ on precedence when a skill exists at both user and project level; AIDF does not rely on that precedence.',
      ],
    };
  }

  async plan(context) {
    const { frameworkRoot, withHooks } = context;
    const actions = [];
    const assets = path.join(frameworkRoot, 'assets');

    const fragment = await readText(path.join(assets, 'core', 'AGENTS.fragment.md'));
    actions.push({
      kind: 'managed-block',
      path: 'AGENTS.md',
      blockId: 'default',
      content: fragment,
      category: 'instructions',
    });

    for (const rule of ['00-core.md', '10-security.md', '20-context.md']) {
      actions.push({
        kind: 'write',
        path: path.posix.join('.qoder', 'rules', `aidf-${rule}`),
        content: await readText(path.join(assets, 'adapters', 'qoder', 'rules', rule)),
        category: 'ide-rules',
      });
    }

    const catalog = await loadSkillCatalog(frameworkRoot);
    for (const skill of catalog) {
      actions.push({
        kind: 'write',
        path: path.posix.join('.qoder', 'skills', skill.name, 'SKILL.md'),
        content: await readText(path.join(assets, 'skills', skill.name, 'SKILL.md')),
        category: 'skills',
      });
    }

    actions.push({
      kind: 'write',
      path: path.posix.join('.qoder', 'agents', 'aidf-orchestrator.md'),
      content: await readText(path.join(assets, 'adapters', 'qoder', 'agents', 'aidf-orchestrator.md')),
      category: 'ide-agents',
    });

    if (withHooks) {
      actions.push({
        kind: 'write',
        path: '.aidf/hooks/session-context.mjs',
        content: await readText(path.join(assets, 'hooks', 'session-context.mjs')),
        category: 'hooks',
      });
      actions.push({
        kind: 'write',
        path: '.aidf/hooks/audit.mjs',
        content: await readText(path.join(assets, 'hooks', 'audit.mjs')),
        category: 'hooks',
      });
      actions.push({
        kind: 'merge-json',
        path: '.qoder/settings.json',
        patch: {
          hooks: {
            SessionStart: [
              {
                hooks: [
                  {
                    type: 'command',
                    command: 'node .aidf/hooks/session-context.mjs',
                    timeout: 15,
                    statusMessage: 'AIDF: loading project context',
                  },
                ],
              },
            ],
            PostToolUse: [
              {
                matcher: 'Write|Edit|search_replace|create_file',
                hooks: [
                  {
                    type: 'command',
                    command: 'node .aidf/hooks/audit.mjs',
                    timeout: 10,
                    async: true,
                  },
                ],
              },
            ],
          },
        },
        category: 'ide-settings',
      });
    }

    return actions;
  }

  async verify(context) {
    const { root, frameworkRoot } = context;
    const checks = [];
    const env = context.env ?? {};
    checks.push({
      id: 'qoder-installed',
      status: env?.ide?.qoder?.installed ? 'PASS' : 'NOT_PROVEN',
      evidence: env?.ide?.qoder?.home ?? null,
      limitation: env?.ide?.qoder?.installed ? null : 'Qoder installation not detected on this machine.',
    });

    const agents = path.join(root, 'AGENTS.md');
    const agentsText = (await exists(agents)) ? await readText(agents) : '';
    checks.push({
      id: 'agents-md-block',
      status: agentsText.includes('<!-- AIDF:BEGIN') ? 'PASS' : 'FAIL',
      evidence: 'AGENTS.md managed block',
    });

    const rulesDir = path.join(root, '.qoder', 'rules');
    const ruleFiles = ['aidf-00-core.md', 'aidf-10-security.md', 'aidf-20-context.md'];
    const presentRules = [];
    for (const file of ruleFiles) {
      if (await exists(path.join(rulesDir, file))) presentRules.push(file);
    }
    checks.push({
      id: 'rule-files',
      status: presentRules.length === ruleFiles.length ? 'PASS' : 'FAIL',
      evidence: presentRules,
      limitation: 'Activation type is not encoded in the rule file; confirm it in the Qoder UI (Always Apply).',
    });

    const catalog = await loadSkillCatalog(frameworkRoot);
    const invalid = [];
    for (const skill of catalog) {
      const file = path.join(root, '.qoder', 'skills', skill.name, 'SKILL.md');
      if (!(await exists(file))) {
        invalid.push(`${skill.name}: missing`);
        continue;
      }
      const validation = validateSkillContent(await readText(file), { directoryName: skill.name });
      if (!validation.valid) invalid.push(`${skill.name}: ${validation.errors.join('; ')}`);
    }
    checks.push({
      id: 'skills-installed',
      status: invalid.length === 0 ? 'PASS' : 'FAIL',
      evidence: `${catalog.length} skills`,
      limitation: invalid.length ? invalid : null,
    });

    const settings = path.join(root, '.qoder', 'settings.json');
    let settingsOk = true;
    let settingsNote = 'not present';
    if (await exists(settings)) {
      try {
        const parsed = JSON.parse(await readText(settings));
        settingsOk = true;
        settingsNote = parsed.hooks ? 'hooks configured' : 'no hooks';
      } catch (error) {
        settingsOk = false;
        settingsNote = error.message;
      }
    }
    checks.push({
      id: 'settings-json',
      status: settingsOk ? 'PASS' : 'FAIL',
      evidence: settingsNote,
    });

    checks.push({
      id: 'runtime-skill-discovery',
      status: 'NOT_PROVEN',
      evidence: null,
      limitation: 'Open a Qoder session and run /skills; confirm aidf-project-context is listed.',
    });
    checks.push({
      id: 'runtime-rule-activation',
      status: 'NOT_PROVEN',
      evidence: null,
      limitation: 'Qoder Settings → Rules: set aidf-00-core and aidf-10-security to Always Apply.',
    });
    checks.push({
      id: 'runtime-hook-activation',
      status: context.withHooks ? 'NOT_PROVEN' : 'NOT_APPLICABLE',
      evidence: null,
      limitation: context.withHooks
        ? 'Restart Qoder and verify a tool edit writes .aidf/logs/audit-<date>.jsonl.'
        : 'Hooks were not requested at install time.',
    });

    const fileChecks = checks.filter((c) => !c.id.startsWith('runtime-'));
    const filesOk = fileChecks.every((c) => c.status === 'PASS' || c.status === 'NOT_APPLICABLE');
    return {
      status: filesOk ? 'CONTRACT-PROVEN' : 'FAIL',
      checks,
      limitations: [
        'File presence and format are proven. Runtime activation in Qoder requires a manual session check.',
      ],
    };
  }
}
