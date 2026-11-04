import { parseArgs } from '../util/args.mjs';
import { resolveProjectRoot, isInitialized } from '../core/paths.mjs';
import { loadConfig } from '../core/config.mjs';
import { assembleContext, renderContextMarkdown, writeContext } from '../core/context.mjs';
import { attachLogFile } from '../util/logger.mjs';
import { resolveInside, writeAtomic } from '../util/fsx.mjs';

export function contextArgSpec() {
  return {
    booleans: ['json', 'help', 'no-write'],
    aliases: { h: 'help', t: 'target' },
  };
}

export async function contextCommand(argv, ctx) {
  const args = parseArgs(argv, contextArgSpec());
  if (args.help) {
    ctx.logger.info('Usage: aidf context --task "<request>" [--write <file>] [--json]');
    return 0;
  }
  const task = args.task ?? args._.join(' ');
  if (!task) {
    ctx.logger.error('Missing --task "<request>".');
    return 1;
  }
  const root = resolveProjectRoot({ target: args.target, cwd: process.cwd() });
  if (!isInitialized(root)) {
    ctx.logger.error(`AIDF is not initialized in ${root}. Run "aidf install" first.`);
    return 1;
  }
  await attachLogFile(ctx.logger, root).catch(() => null);
  const config = await loadConfig(root);
  const context = await assembleContext(root, task, { config, frameworkRoot: ctx.frameworkRoot });
  let written = null;
  if (!args['no-write']) {
    const result = await writeContext(root, context, config);
    written = result.markdownPath;
  }
  if (args.write) {
    const target = resolveInside(root, args.write);
    await writeAtomic(target, renderContextMarkdown(context, config));
    written = args.write;
  }
  if (ctx.json) {
    process.stdout.write(`${JSON.stringify({ context, written }, null, 2)}\n`);
  } else {
    process.stdout.write(renderContextMarkdown(context, config));
    if (written) ctx.logger.info(`Context written to ${written}`);
  }
  if (context.status === 'BLOCKED') {
    ctx.logger.error('Contradiction-sensitive documents are BLOCKED or OUTDATED. Affected decisions are blocked until resolved.');
    return 2;
  }
  return 0;
}
