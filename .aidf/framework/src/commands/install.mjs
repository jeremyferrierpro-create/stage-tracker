import path from 'node:path';
import fsp from 'node:fs/promises';
import { parseArgs, assertEnum } from '../util/args.mjs';
import { slugify } from '../util/text.mjs';
import { ensureDir, exists, writeJson, readJson } from '../util/fsx.mjs';
import { resolveProjectRoot, aidfPaths, AIDF_VERSION } from '../core/paths.mjs';
import { defaultConfig, loadConfig, saveConfig } from '../core/config.mjs';
import { emptyManifest, loadManifest, saveManifest, appendHistory } from '../core/manifest.mjs';
import { planCore, renderInstallReport } from '../core/bootstrap.mjs';
import { Installer, summarizeResults } from '../core/installer.mjs';
import { getAdapter, ADAPTER_KEYS } from '../core/adapters/index.mjs';
import { detectEnvironment, detectProject } from '../core/detect.mjs';
import { buildSkillRegistry, saveProjectSkillRegistry } from '../core/skills.mjs';
import { attachLogFile } from '../util/logger.mjs';

export function installArgSpec() {
  return {
    booleans: ['dry-run', 'force', 'yes', 'json', 'with-hooks', 'update', 'help'],
    aliases: { h: 'help', n: 'name', a: 'adapter', t: 'target' },
  };
}

export async function installCommand(argv, ctx) {
  const args = parseArgs(argv, installArgSpec());
  if (args.help) {
    ctx.logger.info('Usage: aidf install [--target <dir>] [--adapter qoder|generic] [--name <name>] [--with-hooks] [--dry-run] [--force]');
    return 0;
  }
  const adapterKey = assertEnum(args.adapter, ADAPTER_KEYS, 'adapter') ?? 'qoder';
  const root = resolveProjectRoot({ target: args.target, cwd: process.cwd() });
  if (!(await exists(root))) {
    if (!args.dryRun) await ensureDir(root);
  }
  // Dry-run must not create any project file, including logs.
  if (!args['dry-run']) await attachLogFile(ctx.logger, root).catch(() => null);
  const env = await detectEnvironment();
  const project = await detectProject(root);
  const adapter = getAdapter(adapterKey);

  const paths = aidfPaths(root);
  let config = await readJson(paths.config, null);
  const projectName = args.name ?? project.packageJson?.name ?? path.basename(root);
  if (!config) {
    config = defaultConfig({
      name: projectName,
      slug: slugify(projectName),
      adapter: adapterKey,
      commands: {
        test: project.packageJson?.scripts?.test ?? null,
        build: project.packageJson?.scripts?.build ?? null,
        lint: project.packageJson?.scripts?.lint ?? null,
        securityAudit: null,
      },
    });
  } else {
    config.adapter = adapterKey;
    config.framework.version = AIDF_VERSION;
    if (args.name) {
      config.project.name = args.name;
      config.project.slug = slugify(args.name);
    }
    if (!config.commands) {
      config.commands = {
        test: project.packageJson?.scripts?.test ?? null,
        build: project.packageJson?.scripts?.build ?? null,
        lint: project.packageJson?.scripts?.lint ?? null,
        securityAudit: null,
      };
    }
  }

  let manifest = await readJson(paths.manifest, null);
  if (!manifest) manifest = emptyManifest({ name: config.project.name, slug: config.project.slug, adapter: adapterKey });
  manifest.adapter = adapterKey;
  manifest.framework.version = AIDF_VERSION;

  const { actions } = await planCore(root, { frameworkRoot: ctx.frameworkRoot });
  const adapterActions = await adapter.plan({
    root,
    frameworkRoot: ctx.frameworkRoot,
    config,
    withHooks: Boolean(args['with-hooks']),
    env,
  });
  const allActions = [...actions, ...adapterActions];

  const installer = new Installer({
    root,
    manifest,
    logger: ctx.logger,
    dryRun: Boolean(args['dry-run']),
    force: Boolean(args.force),
  });
  const results = await installer.apply(allActions);
  const summary = summarizeResults(results);

  if (!args['dry-run']) {
    await ensureDir(paths.registry);
    await saveConfig(root, config);
    appendHistory(manifest, {
      event: 'install',
      version: AIDF_VERSION,
      adapter: adapterKey,
      summary,
    });
    await saveManifest(root, manifest);

    const registry = await buildSkillRegistry(root, { adapter: adapterKey, frameworkRoot: ctx.frameworkRoot });
    await saveProjectSkillRegistry(root, registry);

    const report = renderInstallReport({
      root,
      projectName: config.project.name,
      adapter: adapterKey,
      results,
      summary,
      version: AIDF_VERSION,
    });
    await fsp.mkdir(paths.reports, { recursive: true });
    await fsp.writeFile(path.join(paths.reports, 'install.md'), report, 'utf8');
  }

  if (ctx.json) {
    process.stdout.write(`${JSON.stringify({ root, adapter: adapterKey, summary, results }, null, 2)}\n`);
  } else {
    ctx.logger.info(`${args['dry-run'] ? '[dry-run] ' : ''}AIDF ${args.update ? 'update' : 'install'} — ${config.project.name} (${adapterKey})`);
    ctx.logger.info(`created=${summary.created} updated=${summary.updated} unchanged=${summary.unchanged} conflicts=${summary.conflicts} failed=${summary.failed} would=${summary.would}`);
    for (const result of results.filter((r) => r.status === 'CONFLICT' || r.status === 'FAILED')) {
      ctx.logger.warn(`${result.status} ${result.path}: ${result.reason ?? ''}`);
    }
    if (summary.conflicts > 0) {
      ctx.logger.warn('Existing files were preserved. Review conflicts, then merge or use --force only for AIDF-managed files.');
    }
    ctx.logger.info('Next: node .aidf/bin/aidf.mjs doctor');
  }
  return summary.failed > 0 ? 1 : summary.conflicts > 0 ? 2 : 0;
}

export async function initCommand(argv, ctx) {
  const args = parseArgs(argv, installArgSpec());
  const positional = args._[0];
  if (positional && !args.target) {
    const index = argv.indexOf(positional);
    const next = [...argv.slice(0, index), '--target', positional, ...argv.slice(index + 1)];
    return installCommand(next, ctx);
  }
  return installCommand(argv, ctx);
}

export async function updateCommand(argv, ctx) {
  const args = parseArgs(argv, installArgSpec());
  args.update = true;
  return installCommand(argv, ctx);
}
