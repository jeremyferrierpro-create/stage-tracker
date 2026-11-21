import path from 'node:path';
import { parseArgs } from '../util/args.mjs';
import { resolveProjectRoot, isInitialized, aidfPaths } from '../core/paths.mjs';
import { attachLogFile } from '../util/logger.mjs';
import { readJson, writeJson, writeAtomic, ensureDir, resolveInside, toPosix } from '../util/fsx.mjs';
import { evaluateTechnologyDecision, exportToJSON, exportToMarkdown } from '../core/technology-decision/index.mjs';
import { validateArtefact } from '../core/artefacts/index.mjs';

export function decisionArgSpec() {
  return {
    booleans: ['json', 'help', 'write', 'markdown'],
    aliases: { h: 'help', t: 'target' },
  };
}

async function loadJsonFile(root, file, label) {
  if (!file) throw new Error(`Missing ${label} file.`);
  const abs = path.isAbsolute(file) ? file : resolveInside(root, file);
  return readJson(abs, null);
}

function toArtefact(document, root) {
  const now = new Date().toISOString();
  const id = String(document.meta?.id ?? `TECH-DECISION-${Date.now()}`).replace(/[^A-Za-z0-9_-]/g, '-');
  return {
    id,
    schemaVersion: '1.0.0',
    createdAt: now,
    updatedAt: now,
    status: 'DRAFT',
    type: 'decision',
    title: document.meta?.id ?? 'Technology decision',
    source: {
      type: 'generated',
      author: 'aidf',
      createdAt: now,
      generator: 'technology-decision@0.3.2',
    },
    links: {},
    document,
    validation: {
      schemaVersion: '1.0.0',
      timestamp: now,
      valid: true,
      errors: [],
      warnings: ['Decision requires human approval before VALIDATED (see document.humanApproval).'],
    },
  };
}

export async function decisionCommand(argv, ctx) {
  const args = parseArgs(argv, decisionArgSpec());
  const sub = args._[0] ?? 'evaluate';
  if (args.help || sub === 'help') {
    ctx.logger.info(
      'Usage: aidf decision evaluate --context <file.json> --options <file.json> [--write] [--markdown] [--out <file>]',
    );
    return 0;
  }
  const root = resolveProjectRoot({ target: args.target, cwd: process.cwd() });
  if (!isInitialized(root)) {
    ctx.logger.error(`AIDF is not initialized in ${root}. Run "aidf install" first.`);
    return 1;
  }
  await attachLogFile(ctx.logger, root).catch(() => null);

  if (sub !== 'evaluate') {
    ctx.logger.error(`Unknown decision subcommand: ${sub}`);
    return 1;
  }

  let context;
  let options;
  try {
    context = await loadJsonFile(root, args.context, '--context');
    const raw = await loadJsonFile(root, args.options, '--options');
    options = { technologyOptions: Array.isArray(raw) ? raw : (raw?.technologyOptions ?? []) };
  } catch (error) {
    ctx.logger.error(error.message);
    return 1;
  }
  if (!context || typeof context !== 'object') {
    ctx.logger.error('--context must be a JSON object (projectType, constraints, skills, ...).');
    return 1;
  }
  if (options.technologyOptions.length === 0) {
    ctx.logger.error('--options must contain at least one technology option.');
    return 1;
  }

  const document = await evaluateTechnologyDecision(context, options);

  if (args.out) {
    try {
      const abs = path.isAbsolute(args.out) ? args.out : resolveInside(root, args.out);
      await ensureDir(path.dirname(abs));
      if (args.markdown) {
        await writeAtomic(abs, exportToMarkdown(document));
      } else {
        await writeJson(abs, JSON.parse(exportToJSON(document)));
      }
      ctx.logger.info(`Decision written to ${toPosix(abs)}`);
    } catch (error) {
      ctx.logger.error(`Cannot write --out: ${error.message}`);
      return 1;
    }
  }

  let artefactPath = null;
  if (args.write) {
    const artefact = toArtefact(document, root);
    const validation = await validateArtefact(artefact);
    if (!validation.valid) {
      ctx.logger.error(`Generated artefact failed validation: ${validation.errors.join('; ')}`);
      return 1;
    }
    const dir = aidfPaths(root).artefacts.decisions;
    await ensureDir(dir);
    artefactPath = path.join(dir, `${artefact.id}.json`);
    await writeJson(artefactPath, artefact);
  }

  if (ctx.json) {
    process.stdout.write(`${JSON.stringify({ document, artefact: artefactPath ? toPosix(artefactPath) : null }, null, 2)}\n`);
    return 0;
  }

  const rec = document.recommendation ?? {};
  ctx.logger.info(`Decision ${document.meta?.id ?? ''} — confidence ${rec.confidence ?? 'unknown'}`);
  for (const s of rec.stack ?? []) ctx.logger.info(`  ${s.component}: ${s.option} (${s.reason ?? ''})`);
  ctx.logger.info(
    `Options: ${document.analysis?.deterministic?.summary?.totalOptions ?? '?'} total, `
    + `${document.analysis?.deterministic?.summary?.validOptions ?? '?'} valid, `
    + `${document.analysis?.deterministic?.summary?.eliminatedOptions ?? '?'} eliminated`,
  );
  for (const u of rec.uncertainties ?? []) ctx.logger.warn(`uncertainty: ${JSON.stringify(u)}`);
  if (document.humanApproval?.required && !document.humanApproval?.approved) {
    ctx.logger.warn('Human approval required before this decision is VALIDATED.');
  }
  if (artefactPath) ctx.logger.info(`Artefact: ${toPosix(artefactPath)}`);
  return 0;
}
