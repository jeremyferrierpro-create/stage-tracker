import path from 'node:path';
import { parseArgs } from '../util/args.mjs';
import { resolveProjectRoot, isInitialized, FRAMEWORK_ROOT } from '../core/paths.mjs';
import { loadSources, resourcesStatus, setupResources } from '../core/resources.mjs';
import {
  inventoryAll, coverageReport, summarizeInventory, writeInventory, readInventory, reconcile,
} from '../core/resource-inventory.mjs';
import { attachLogFile } from '../util/logger.mjs';

export function resourcesArgSpec() {
  return {
    booleans: ['json', 'dry-run', 'help', 'write', 'reconcile'],
    aliases: { h: 'help', t: 'target' },
  };
}

function candidateRoots(root, frameworkRoot) {
  return [root, path.dirname(root), path.dirname(frameworkRoot), frameworkRoot];
}

async function buildInventory(root, ctx, args = {}) {
  const sources = await loadSources(ctx.frameworkRoot ?? FRAMEWORK_ROOT);
  const maxFileBytes = args['max-file-bytes'] ? Number(args['max-file-bytes']) : undefined;
  const results = await inventoryAll(sources, {
    roots: candidateRoots(root, ctx.frameworkRoot ?? FRAMEWORK_ROOT),
    maxFileBytes,
  });
  return { sources, results };
}

export async function resourcesCommand(argv, ctx) {
  const args = parseArgs(argv, resourcesArgSpec());
  const sub = args._[0] ?? 'list';
  if (args.help) {
    ctx.logger.info('Usage: aidf resources <list|inspect|inventory|coverage|reconcile> [--write] [--json]');
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

  if (sub === 'inventory') {
    const { results } = await buildInventory(root, ctx, args);
    const summary = summarizeInventory(results);
    let written = null;
    if (args.write && !args['dry-run']) {
      written = await writeInventory(root, results);
    }
    if (ctx.json) {
      process.stdout.write(`${JSON.stringify({ summary, written: written?.file ?? null, resources: results }, null, 2)}\n`);
    } else {
      ctx.logger.info(`Inventory: ${summary.resources} resources, ${summary.files} files, ${summary.skills} skills`);
      ctx.logger.table(
        results.map((r) => ({
          id: r.id,
          status: r.status,
          license: r.license,
          files: r.fileCount,
          mb: Math.round((r.totalBytes / (1024 * 1024)) * 10) / 10,
          hash: r.treeHash ? r.treeHash.slice(0, 12) : '-',
        })),
        [
          { key: 'id', label: 'Resource' },
          { key: 'status', label: 'Status' },
          { key: 'license', label: 'Licence' },
          { key: 'files', label: 'Files' },
          { key: 'mb', label: 'MB' },
          { key: 'hash', label: 'Tree hash' },
        ],
      );
      if (written) ctx.logger.info(`Manifest written: ${written.file}`);
      else ctx.logger.info('Dry run: manifest not written (add --write).');
    }
    return 0;
  }

  if (sub === 'coverage') {
    const { sources, results } = await buildInventory(root, ctx, args);
    const coverage = coverageReport(sources, results);
    if (ctx.json) process.stdout.write(`${JSON.stringify(coverage, null, 2)}\n`);
    else {
      ctx.logger.info(
        `Coverage: ${coverage.inventoriedResources}/${coverage.declaredResources} resources, `
        + `${coverage.uniqueSkills} unique skills, ${coverage.totalSkillFiles} SKILL.md files`,
      );
      if (coverage.missing.length > 0) ctx.logger.error(`Missing from inventory: ${coverage.missing.join(', ')}`);
      if (coverage.blockedResources.length > 0) ctx.logger.warn(`Blocked: ${coverage.blockedResources.join(', ')}`);
      if (coverage.duplicateSkills.length > 0) {
        ctx.logger.info(`Duplicate skill names: ${coverage.duplicateSkills.map((d) => d.name).join(', ')}`);
      }
      ctx.logger.info(`Complete: ${coverage.complete ? 'YES' : 'NO'}`);
    }
    return coverage.complete ? 0 : 1;
  }

  if (sub === 'reconcile') {
    const lock = await resourcesStatus(root);
    if (!lock) {
      ctx.logger.warn('No resources lock found. Run `aidf setup resources` first.');
      return 1;
    }
    let manifest = await readInventory(root);
    if (!manifest || args.reconcile) {
      const { results } = await buildInventory(root, ctx, args);
      if (args.write && !args['dry-run']) await writeInventory(root, results);
      manifest = { resources: results };
    }
    const report = reconcile(lock, manifest);
    if (ctx.json) process.stdout.write(`${JSON.stringify(report, null, 2)}\n`);
    else {
      ctx.logger.info(`Reconcile: ${report.checked} locked resources, ${report.drift.length} drift(s)`);
      for (const item of report.drift) ctx.logger.warn(`${item.id}: ${item.kind}`);
    }
    return report.drift.length === 0 ? 0 : 1;
  }

  ctx.logger.error(`Unknown resources subcommand: ${sub}`);
  return 1;
}
