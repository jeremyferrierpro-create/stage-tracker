import path from 'node:path';
import { parseArgs } from '../util/args.mjs';
import { resolveProjectRoot, isInitialized } from '../core/paths.mjs';
import { loadConfig } from '../core/config.mjs';
import { setupResources, loadSources } from '../core/resources.mjs';
import { attachLogFile } from '../util/logger.mjs';

export function setupArgSpec() {
  return {
    booleans: ['dry-run', 'json', 'yes', 'allow-network', 'help'],
    aliases: { h: 'help', t: 'target' },
  };
}

export async function setupCommand(argv, ctx) {
  const args = parseArgs(argv, setupArgSpec());
  if (args.help) {
    ctx.logger.info('Usage: aidf setup [resources] [--target <dir>] [--dry-run] [--allow-network]');
    return 0;
  }
  const sub = args._[0];
  if (sub && sub !== 'resources') {
    ctx.logger.error(`Unknown setup target "${sub}". Available: resources`);
    return 1;
  }
  const root = resolveProjectRoot({ target: args.target, cwd: process.cwd() });
  if (!isInitialized(root)) {
    ctx.logger.error(`AIDF is not initialized in ${root}. Run "aidf install" first.`);
    return 1;
  }
  // A dry-run must not write anything, including logs.
  if (!args['dry-run']) await attachLogFile(ctx.logger, root).catch(() => null);
  const config = await loadConfig(root);
  if (args['allow-network']) {
    config.security.allowNetwork = true;
  }
  const { results, lock } = await setupResources(root, {
    dryRun: Boolean(args['dry-run']),
    frameworkRoot: ctx.frameworkRoot,
  });
  if (ctx.json) {
    process.stdout.write(`${JSON.stringify({ results, lock }, null, 2)}\n`);
  } else {
    ctx.logger.info(`${args['dry-run'] ? '[dry-run] ' : ''}AIDF resources`);
    ctx.logger.table(
      results.map((r) => ({
        id: r.id,
        status: r.status,
        kind: r.kind,
        license: r.license,
        path: r.path ?? '-',
      })),
      [
        { key: 'id', label: 'Resource' },
        { key: 'status', label: 'Status' },
        { key: 'kind', label: 'Kind' },
        { key: 'license', label: 'Licence' },
        { key: 'path', label: 'Path' },
      ],
    );
    const blocked = results.filter((r) => ['BLOCKED', 'ABSENT'].includes(r.status));
    if (blocked.length > 0) {
      ctx.logger.warn(`${blocked.length} resource(s) are not usable: ${blocked.map((r) => r.id).join(', ')}`);
    }
    ctx.logger.info('Lock written to .aidf/registry/resources.lock.json (unless dry-run).');
  }
  return 0;
}
