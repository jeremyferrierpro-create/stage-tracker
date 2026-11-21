import { parseArgs } from '../util/args.mjs';
import { resolveProjectRoot, isInitialized } from '../core/paths.mjs';
import { loadConfig } from '../core/config.mjs';
import {
  recordEvidence, listEvidence, verifyEvidenceChain, captureFiles,
} from '../core/evidence.mjs';
import { attachLogFile } from '../util/logger.mjs';

export function evidenceArgSpec() {
  return {
    booleans: ['json', 'human', 'help', 'verify'],
    aliases: { h: 'help', t: 'target' },
  };
}

export async function evidenceCommand(argv, ctx) {
  const args = parseArgs(argv, evidenceArgSpec());
  const sub = args._[0] ?? 'list';
  if (args.help) {
    ctx.logger.info('Usage: aidf evidence <list|show|capture|add|verify> [options]');
    ctx.logger.info('  add --type <type> --note "..." [--human --by <name>]');
    ctx.logger.info('  capture --type implementation-change --note "..."');
    return 0;
  }
  const root = resolveProjectRoot({ target: args.target, cwd: process.cwd() });
  if (!isInitialized(root)) {
    ctx.logger.error(`AIDF is not initialized in ${root}. Run "aidf install" first.`);
    return 1;
  }
  await attachLogFile(ctx.logger, root).catch(() => null);
  const config = await loadConfig(root);

  if (sub === 'list') {
    const entries = await listEvidence(root, { type: args.type, status: args.status, gate: args.gate });
    if (ctx.json) process.stdout.write(`${JSON.stringify(entries, null, 2)}\n`);
    else {
      ctx.logger.info(`Evidence (${entries.length})`);
      ctx.logger.table(
        entries.map((e) => ({
          id: e.id.slice(0, 22),
          type: e.type,
          status: e.status,
          human: e.humanApproval ? 'yes' : 'no',
          created: e.createdAt,
        })),
        [
          { key: 'id', label: 'ID' },
          { key: 'type', label: 'Type' },
          { key: 'status', label: 'Status' },
          { key: 'human', label: 'Human' },
          { key: 'created', label: 'Created' },
        ],
      );
    }
    return 0;
  }

  if (sub === 'verify') {
    const chain = await verifyEvidenceChain(root);
    const bad = chain.problems.map((p) => ({
      file: p.file,
      reason: `${p.code}: ${p.reason}`,
    }));
    if (ctx.json) {
      process.stdout.write(`${JSON.stringify({
        ok: chain.ok,
        checked: chain.checked,
        problems: chain.problems,
        bad,
      }, null, 2)}\n`);
    } else {
      ctx.logger.info(`Verified ${chain.checked} evidence record(s) and the chain.`);
      for (const item of bad) ctx.logger.error(`${item.file ?? 'index'}: ${item.reason}`);
    }
    return chain.ok ? 0 : 1;
  }

  if (sub === 'show') {
    const id = args._[1];
    const entries = await listEvidence(root);
    const entry = entries.find((e) => e.id === id || e.id.startsWith(id ?? ''));
    if (!entry) {
      ctx.logger.error(`Unknown evidence: ${id}`);
      return 1;
    }
    const { readJson } = await import('../util/fsx.mjs');
    const body = await readJson(`${root}/${entry.file}`, null);
    process.stdout.write(`${JSON.stringify(body, null, 2)}\n`);
    return 0;
  }

  if (sub === 'capture') {
    const type = args.type;
    if (!type) {
      ctx.logger.error('Missing --type (for example implementation-change).');
      return 1;
    }
    const captured = await captureFiles(root, { since: args.since });
    if (captured.files.length === 0) {
      ctx.logger.warn('No changed files detected. Evidence was not recorded (NOT_PROVEN).');
      return 1;
    }
    const evidence = await recordEvidence(root, {
      type,
      status: args.status ?? 'PASS',
      summary: args.note ?? `${captured.files.length} changed file(s) captured from ${captured.source}.`,
      note: args.note ?? '',
      createdBy: args.human ? 'human' : 'aidf',
      humanApproval: Boolean(args.human),
      approvedBy: args.by ?? null,
      approval: args.human
        ? {
            declaredBy: args.by ?? null,
            declaredAt: new Date().toISOString(),
            authenticated: false,
            method: 'declared',
            keyId: null,
          }
        : { declaredBy: null, declaredAt: null, authenticated: false, method: 'none', keyId: null },
      files: captured.files,
      checks: [{
        id: 'files-captured',
        status: 'PASS',
        reason: `${captured.files.length} file(s) from ${captured.source}`,
      }],
      limitations: ['File list is not a review of the content or quality of the change.'],
    });
    ctx.logger.info(`Evidence recorded: ${evidence.file}`);
    return 0;
  }

  if (sub === 'add') {
    const type = args.type;
    if (!type) {
      ctx.logger.error('Missing --type.');
      return 1;
    }
    if (args.human && !args.by) {
      ctx.logger.error('Human evidence requires --by "<name>" for accountability.');
      return 1;
    }
    if (args.human && config.security?.requireHumanApprovalFor?.length) {
      // human approval is allowed for any type; the gate decides which types require it
    }
    const evidence = await recordEvidence(root, {
      type,
      status: args.status ?? 'PASS',
      summary: args.note ?? `${type} recorded manually.`,
      note: args.note ?? '',
      createdBy: args.human ? 'human' : 'agent',
      humanApproval: Boolean(args.human),
      approvedBy: args.by ?? null,
      approval: args.human
        ? {
            declaredBy: args.by ?? null,
            declaredAt: new Date().toISOString(),
            authenticated: false,
            method: 'declared',
            keyId: null,
          }
        : { declaredBy: null, declaredAt: null, authenticated: false, method: 'none', keyId: null },
      files: [],
      checks: [{
        id: 'manual-record',
        status: args.human ? 'PASS' : 'NOT_PROVEN',
        reason: args.human ? `Approved by ${args.by}` : 'Agent declaration only; not a human approval.',
      }],
      limitations: args.human
        ? ['Declared approval is not authenticated: it records intent, not proof that a human performed the action.']
        : ['Agent-declared evidence is not human approval.'],
    });
    ctx.logger.info(`Evidence recorded: ${evidence.file}`);
    return 0;
  }

  ctx.logger.error(`Unknown evidence subcommand: ${sub}`);
  return 1;
}
