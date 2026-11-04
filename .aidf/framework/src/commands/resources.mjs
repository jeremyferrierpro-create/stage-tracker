import { parseArgs } from '../util/args.mjs';
import { resolveProjectRoot, isInitialized } from '../core/paths.mjs';
import { resourcesStatus, setupResources } from '../core/resources.mjs';
import { attachLogFile } from '../util/logger.mjs';

export function resourcesArgSpec() {
  return {
    booleans: ['json', 'dry-run', 'help'],
    aliases: { h: 'help', t: 'target' },
  };
}

export async function resourcesCommand(argv, ctx) {
  const args = parseArgs(argv, resourcesArgSpec());
  const sub = args._[0] ?? 'list';
  if (args.help) {
    ctx.logger.info('Usage: aidf resources <list|inspect> [--dry-run] [--json]');
    return 0;
  }
  const root = resolveProjectRoot({ target: args.target, cwd: process.cwd() });
  if (!isInitialized(root)) {
    ctx.logger.error(`AIDF is not initialized in ${root}. Run "aidf install" first.`);
    return 1;
  }
  await attachLogFile(ctx.logger, root).catch(() => null);

  if (sub === 'list') {
    const lock = await resourcesStatus(root);
    if (!lock) {
      ctx.logger.warn('No resources lock found. Run `aidf setup resources`.');
      return 1;
    }
    const rows = Object.values(lock.resources ?? {}).map((r) => ({
      id: r.id,
      status: r.status,
      license: r.license,
      version: r.version,
      commit: r.commit ? String(r.commit).slice(0, 8) : '-',
      path: r.path ?? '-',
    }));
    if (ctx.json) process.stdout.write(`${JSON.stringify({ lock, rows }, null, 2)}\n`);
    else {
      ctx.logger.info(`Resources (${rows.length}) — lock generated ${lock.generatedAt ?? 'never'}`);
      ctx.logger.table(rows, [
        { key: 'id', label: 'Resource' },
        { key: 'status', label: 'Status' },
        { key: 'license', label: 'Licence' },
        { key: 'version', label: 'Version' },
        { key: 'commit', label: 'Commit' },
        { key: 'path', label: 'Path' },
      ]);
    }
    return 0;
  }

  if (sub === 'inspect') {
    const { results } = await setupResources(root, {
      dryRun: true,
      frameworkRoot: ctx.frameworkRoot,
    });
    if (ctx.json) process.stdout.write(`${JSON.stringify(results, null, 2)}\n`);
    else {
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
      for (const r of results.filter((x) => x.status === 'BLOCKED')) {
        ctx.logger.warn(`${r.id}: ${r.note}`);
      }
    }
    return 0;
  }

  ctx.logger.error(`Unknown resources subcommand: ${sub}`);
  return 1;
}
