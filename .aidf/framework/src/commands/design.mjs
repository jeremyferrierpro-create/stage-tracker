import path from 'node:path';
import { parseArgs } from '../util/args.mjs';
import { resolveProjectRoot, isInitialized } from '../core/paths.mjs';
import { attachLogFile } from '../util/logger.mjs';
import { readJson, writeAtomic, writeJson, ensureDir, resolveInside, toPosix, timestampSlug } from '../util/fsx.mjs';
import { runDesignWorkflow } from '../core/design-engineering/index.mjs';
import { classifyComplexity, getWorkflow } from '../core/design-engineering/complexity-classifier.mjs';

export function designArgSpec() {
  return {
    booleans: ['json', 'help', 'write'],
    aliases: { h: 'help', t: 'target' },
  };
}

function slugify(name) {
  return String(name ?? 'screen').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') || 'screen';
}

/**
 * Persist fallback outputs (wireframes, mockups, documentation) under
 * .aidf/reports/design/<timestamp>/. Returns the list of written files.
 */
async function writeOutputs(root, result, outDir) {
  const files = [];
  const outputs = result.outputs ?? {};
  const base = outDir ?? path.join(root, '.aidf', 'reports', 'design', timestampSlug());
  await ensureDir(base);

  for (const wireframe of outputs.wireframes ?? []) {
    const file = path.join(base, `${slugify(wireframe.name)}.svg`);
    await writeAtomic(file, wireframe.svg ?? '');
    files.push(toPosix(file));
  }
  if (outputs.mockups) {
    const file = path.join(base, 'mockup.css');
    await writeAtomic(file, typeof outputs.mockups === 'string' ? outputs.mockups : JSON.stringify(outputs.mockups, null, 2));
    files.push(toPosix(file));
  }
  if (outputs.documentation) {
    const file = path.join(base, 'FALLBACK.md');
    await writeAtomic(file, outputs.documentation);
    files.push(toPosix(file));
  }
  await writeJson(path.join(base, 'result.json'), {
    status: result.status,
    complexity: result.complexity,
    workflow: result.workflow,
    figmaStatus: result.figmaStatus,
    files,
  });
  files.push(toPosix(path.join(base, 'result.json')));
  return { dir: toPosix(base), files };
}

export async function designCommand(argv, ctx) {
  const args = parseArgs(argv, designArgSpec());
  const sub = args._[0] ?? 'run';
  if (args.help) {
    ctx.logger.info(
      'Usage: aidf design <run|classify> --context <file.json> [--out <dir>] [--write]',
    );
    return 0;
  }
  const root = resolveProjectRoot({ target: args.target, cwd: process.cwd() });
  if (!isInitialized(root)) {
    ctx.logger.error(`AIDF is not initialized in ${root}. Run "aidf install" first.`);
    return 1;
  }
  await attachLogFile(ctx.logger, root).catch(() => null);

  let context;
  if (args.context) {
    try {
      const abs = path.isAbsolute(args.context) ? args.context : resolveInside(root, args.context);
      context = await readJson(abs, null);
    } catch (error) {
      ctx.logger.error(`Cannot read --context: ${error.message}`);
      return 1;
    }
  }
  if (!context || typeof context !== 'object') {
    ctx.logger.error('--context <file.json> is required (featureCount, teamSize, timelineWeeks, budget, screens, theme, figma, ...).');
    return 1;
  }

  if (sub === 'classify') {
    const complexity = classifyComplexity(context);
    const workflow = getWorkflow(complexity.complexity);
    const payload = { complexity, workflow };
    if (ctx.json) {
      process.stdout.write(`${JSON.stringify(payload, null, 2)}\n`);
    } else {
      ctx.logger.info(`Complexity: ${complexity.complexity} (score ${complexity.score}) → workflow '${workflow}'`);
    }
    return 0;
  }

  if (sub === 'run') {
    const result = await runDesignWorkflow(context);
    let written = null;
    if (args.write || args.out) {
      try {
        const outDir = args.out
          ? (path.isAbsolute(args.out) ? args.out : resolveInside(root, args.out))
          : undefined;
        written = await writeOutputs(root, result, outDir);
      } catch (error) {
        ctx.logger.error(`Cannot write outputs: ${error.message}`);
        return 1;
      }
    }
    const payload = {
      status: result.status,
      complexity: result.complexity,
      workflow: result.workflow,
      figmaStatus: result.figmaStatus,
      reasons: result.reasons ?? [],
      written,
    };
    if (ctx.json) {
      process.stdout.write(`${JSON.stringify({ ...payload, outputs: result.outputs ? Object.keys(result.outputs) : [] }, null, 2)}\n`);
    } else {
      ctx.logger.info(`Design workflow: ${result.status} (complexity ${result.complexity?.complexity}, workflow ${result.workflow})`);
      for (const r of result.reasons ?? []) ctx.logger.warn(`  - ${r}`);
      if (result.figmaStatus && result.figmaStatus.status !== 'fully_verified') {
        ctx.logger.warn(`Figma: ${result.figmaStatus.status} — fallback outputs only; no real Figma file was created.`);
      }
      if (written) {
        ctx.logger.info(`Outputs written to ${written.dir}:`);
        for (const f of written.files) ctx.logger.info(`  - ${f}`);
      } else {
        ctx.logger.info('Dry run: outputs not written (add --write or --out <dir>).');
      }
    }
    return 0;
  }

  ctx.logger.error(`Unknown design subcommand: ${sub}`);
  return 1;
}
