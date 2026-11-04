import { parseArgs, assertEnum } from '../util/args.mjs';
import { resolveProjectRoot, isInitialized } from '../core/paths.mjs';
import { loadConfig } from '../core/config.mjs';
import {
  buildSkillRegistry, saveProjectSkillRegistry, loadDependencies, validateRegistry,
  linkSkill, SKILL_STATUSES,
} from '../core/skills.mjs';
import { loadManifest, saveManifest } from '../core/manifest.mjs';
import { attachLogFile } from '../util/logger.mjs';

export function skillsArgSpec() {
  return {
    booleans: ['json', 'dry-run', 'help'],
    aliases: { h: 'help', t: 'target' },
  };
}

export async function skillsCommand(argv, ctx) {
  const args = parseArgs(argv, skillsArgSpec());
  const sub = args._[0] ?? 'list';
  if (args.help) {
    ctx.logger.info('Usage: aidf skills <list|validate|refresh|show|link> [id] [--status <status>] [--json]');
    return 0;
  }
  const root = resolveProjectRoot({ target: args.target, cwd: process.cwd() });
  if (!isInitialized(root)) {
    ctx.logger.error(`AIDF is not initialized in ${root}. Run "aidf install" first.`);
    return 1;
  }
  await attachLogFile(ctx.logger, root).catch(() => null);
  const config = await loadConfig(root);
  const registry = await buildSkillRegistry(root, { adapter: config.adapter, frameworkRoot: ctx.frameworkRoot });

  if (sub === 'list') {
    const status = assertEnum(args.status, SKILL_STATUSES, 'status');
    const skills = registry.skills.filter((s) => !status || s.status === status);
    if (ctx.json) {
      process.stdout.write(`${JSON.stringify({ skills, summary: registry.summary }, null, 2)}\n`);
    } else {
      ctx.logger.info(`Skills (${skills.length}) — ${JSON.stringify(registry.summary)}`);
      ctx.logger.table(
        skills.map((s) => ({
          id: s.id,
          status: s.status,
          source: s.source,
          path: s.path ?? '-',
        })),
        [
          { key: 'id', label: 'Skill' },
          { key: 'status', label: 'Status' },
          { key: 'source', label: 'Source' },
          { key: 'path', label: 'Path' },
        ],
      );
    }
    return 0;
  }

  if (sub === 'refresh' || sub === 'index') {
    if (!args['dry-run']) await saveProjectSkillRegistry(root, registry);
    ctx.logger.info(`${args['dry-run'] ? '[dry-run] ' : ''}Skill registry refreshed: ${registry.summary.AVAILABLE ?? 0} available, ${registry.summary.BLOCKED ?? 0} blocked.`);
    return 0;
  }

  if (sub === 'validate') {
    const deps = await loadDependencies(ctx.frameworkRoot);
    const validation = await validateRegistry(root, registry, deps);
    if (ctx.json) {
      process.stdout.write(`${JSON.stringify({ validation, registry }, null, 2)}\n`);
    } else {
      ctx.logger.info(`Skill validation: ${validation.valid ? 'PASS' : 'FAIL'}`);
      for (const error of validation.errors) ctx.logger.error(error);
      for (const warning of validation.warnings) ctx.logger.warn(warning);
      if (validation.cycles.length > 0) {
        for (const cycle of validation.cycles) ctx.logger.error(`Cycle: ${cycle.join(' -> ')}`);
      }
      const external = registry.skills.filter((s) => s.source === 'external');
      ctx.logger.info(`External skills discovered: ${external.length} (indexed, not copied).`);
    }
    return validation.valid ? 0 : 1;
  }

  if (sub === 'show') {
    const id = args._[1];
    const skill = registry.skills.find((s) => s.id === id || s.name === id);
    if (!skill) {
      ctx.logger.error(`Unknown skill: ${id}`);
      return 1;
    }
    if (ctx.json) {
      process.stdout.write(`${JSON.stringify(skill, null, 2)}\n`);
    } else {
      ctx.logger.info(`${skill.name} (${skill.id}) — ${skill.status}`);
      ctx.logger.info(`Source: ${skill.source}`);
      ctx.logger.info(`Path: ${skill.path ?? '-'}`);
      ctx.logger.info(`Phases: ${(skill.phases ?? []).join(', ') || '-'}`);
      ctx.logger.info(`Documents: ${(skill.documents ?? []).join(', ') || '-'}`);
      ctx.logger.info(`Description: ${skill.description}`);
      if (skill.errors?.length) ctx.logger.warn(`Errors: ${skill.errors.join('; ')}`);
      if (skill.warnings?.length) ctx.logger.warn(`Warnings: ${skill.warnings.join('; ')}`);
    }
    return 0;
  }

  if (sub === 'link') {
    const id = args._[1];
    if (!id) {
      ctx.logger.error('Usage: aidf skills link <skill-id>');
      return 1;
    }
    try {
      const result = await linkSkill(root, registry, id, { dryRun: Boolean(args['dry-run']) });
      if (result.status === 'LINKED' && !args['dry-run']) {
        const manifest = await loadManifest(root);
        manifest.links[result.path] = { target: result.target, ts: new Date().toISOString() };
        await saveManifest(root, manifest);
      }
      ctx.logger.info(`${result.status}: ${result.path} -> ${result.target}`);
      return 0;
    } catch (error) {
      ctx.logger.error(error.message);
      return 1;
    }
  }

  ctx.logger.error(`Unknown skills subcommand: ${sub}`);
  return 1;
}
