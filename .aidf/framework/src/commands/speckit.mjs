import { parseArgs } from '../util/args.mjs';
import { resolveProjectRoot, isInitialized } from '../core/paths.mjs';
import { specKitStatus, initSpecKit, syncSpecKit } from '../core/speckit.mjs';
import { detectEnvironment } from '../core/detect.mjs';
import { attachLogFile } from '../util/logger.mjs';

export function speckitArgSpec() {
  return {
    booleans: ['json', 'dry-run', 'allow-exec', 'adopt', 'specify-force', 'help'],
    aliases: { h: 'help', t: 'target' },
  };
}

export async function speckitCommand(argv, ctx) {
  const args = parseArgs(argv, speckitArgSpec());
  const sub = args._[0] ?? 'status';
  if (args.help) {
    ctx.logger.info('Usage: aidf speckit <status|init|sync> [--dry-run] [--allow-exec] [--specify-force] [--adopt] [--json]');
    return 0;
  }
  const root = resolveProjectRoot({ target: args.target, cwd: process.cwd() });
  if (!isInitialized(root)) {
    ctx.logger.error(`AIDF is not initialized in ${root}. Run "aidf install" first.`);
    return 1;
  }
  await attachLogFile(ctx.logger, root).catch(() => null);
  const env = await detectEnvironment();
  const options = {
    frameworkRoot: ctx.frameworkRoot,
    specifyPath: env.specKit.cliPath ?? 'specify',
    integration: args.integration ?? 'qodercli',
    dryRun: Boolean(args['dry-run']),
    allowExec: Boolean(args['allow-exec']),
    adopt: Boolean(args.adopt),
    specifyForce: Boolean(args['specify-force']),
  };

  if (sub === 'status') {
    const status = await specKitStatus(root, options);
    if (ctx.json) {
      process.stdout.write(`${JSON.stringify(status, null, 2)}\n`);
    } else {
      ctx.logger.info(`Spec Kit: ${status.status}`);
      ctx.logger.info(`- .specify present: ${status.present}`);
      ctx.logger.info(`- templates: ${status.templates}`);
      ctx.logger.info(`- constitution: ${status.constitutionPresent ? status.constitutionSync : 'absent'}`);
      ctx.logger.info(`- integration skills: ${status.integrationSkills.join(', ') || 'none'}`);
      ctx.logger.info(`- CLI: ${status.cli.available ? `${status.cli.path} (${status.cli.version})` : 'not available'}`);
      if (!status.present) ctx.logger.info('Run `aidf speckit init --allow-exec` to initialise Spec Kit in this project.');
    }
    return 0;
  }

  if (sub === 'init') {
    const result = await initSpecKit(root, options);
    if (ctx.json) process.stdout.write(`${JSON.stringify(result, null, 2)}\n`);
    else {
      ctx.logger.info(`${result.status}: ${result.reason ?? result.command ?? ''}`);
      if (result.status === 'CREATED') ctx.logger.info('Spec Kit initialised. Run `aidf speckit sync --adopt` to publish the AIDF constitution.');
    }
    return result.status === 'FAILED' || result.status === 'BLOCKED' ? 1 : 0;
  }

  if (sub === 'sync') {
    const result = await syncSpecKit(root, options);
    if (ctx.json) process.stdout.write(`${JSON.stringify(result, null, 2)}\n`);
    else {
      ctx.logger.info(`Spec Kit sync: ${result.status}`);
      for (const row of result.results) {
        ctx.logger.info(`${row.status} ${row.path}${row.reason ? ` — ${row.reason}` : ''}`);
      }
    }
    return result.status === 'CONFLICT' ? 2 : 0;
  }

  ctx.logger.error(`Unknown speckit subcommand: ${sub}`);
  return 1;
}
