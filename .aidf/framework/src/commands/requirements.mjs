import { parseArgs, assertEnum } from '../util/args.mjs';
import { resolveProjectRoot, isInitialized } from '../core/paths.mjs';
import { attachLogFile } from '../util/logger.mjs';
import {
  loadRequirementsRegistry,
  addRequirement,
  updateRequirement,
  deleteRequirement,
  calculateCoverage,
  filterRequirements,
  REQUIREMENT_TYPES,
  REQUIREMENT_STATUSES,
  REQUIREMENT_PRIORITIES,
} from '../core/requirements/registry.mjs';

export function requirementsArgSpec() {
  return {
    booleans: ['json', 'help', 'human', 'yes'],
    aliases: { h: 'help', t: 'target' },
  };
}

function printJson(value) {
  process.stdout.write(`${JSON.stringify(value, null, 2)}\n`);
}

function parseCriteria(raw) {
  if (!raw) return [];
  try {
    const parsed = JSON.parse(raw);
    if (Array.isArray(parsed)) return parsed;
  } catch {
    // fall through: treat as a single criterion text
  }
  return [{ id: 'AC-1', description: String(raw) }];
}

export async function requirementsCommand(argv, ctx) {
  const args = parseArgs(argv, requirementsArgSpec());
  const sub = args._[0] ?? 'list';
  if (args.help) {
    ctx.logger.info(
      'Usage: aidf requirements <list|show|add|update|delete|coverage> '
      + '[--type FUNCTIONAL|NON_FUNCTIONAL] [--status S] [--priority P] [--title t] [--ac "..."]',
    );
    return 0;
  }
  const root = resolveProjectRoot({ target: args.target, cwd: process.cwd() });
  if (!isInitialized(root)) {
    ctx.logger.error(`AIDF is not initialized in ${root}. Run "aidf install" first.`);
    return 1;
  }
  await attachLogFile(ctx.logger, root).catch(() => null);

  if (sub === 'list') {
    const registry = await loadRequirementsRegistry(root);
    const rows = filterRequirements(registry, {
      type: args.type ? assertEnum(args.type, REQUIREMENT_TYPES, 'type') : undefined,
      status: args.status ? assertEnum(args.status, REQUIREMENT_STATUSES, 'status') : undefined,
      priority: args.priority ? assertEnum(args.priority, REQUIREMENT_PRIORITIES, 'priority') : undefined,
    }).map((r) => ({
      id: r.id,
      type: r.type,
      status: r.status,
      priority: r.priority,
      title: r.title,
    }));
    if (ctx.json) {
      printJson({ count: rows.length, requirements: rows });
    } else {
      ctx.logger.info(`Requirements (${rows.length})`);
      ctx.logger.table(rows, [
        { key: 'id', label: 'ID' },
        { key: 'type', label: 'Type' },
        { key: 'status', label: 'Status' },
        { key: 'priority', label: 'Priority' },
        { key: 'title', label: 'Title' },
      ]);
    }
    return 0;
  }

  if (sub === 'show') {
    const id = args._[1];
    if (!id) {
      ctx.logger.error('Usage: aidf requirements show <id>');
      return 1;
    }
    const registry = await loadRequirementsRegistry(root);
    const req = registry.requirements.find((r) => r.id === id);
    if (!req) {
      ctx.logger.error(`Requirement not found: ${id}`);
      return 1;
    }
    printJson(req);
    return 0;
  }

  if (sub === 'add') {
    const type = assertEnum(args.type, REQUIREMENT_TYPES, 'type');
    if (!type || !args.title) {
      ctx.logger.error('Usage: aidf requirements add --type FUNCTIONAL|NON_FUNCTIONAL --title "..." [--description "..."] [--priority must|should|could] [--ac "criterion"]');
      return 1;
    }
    const requirement = {
      type,
      title: args.title,
      description: args.description ?? '',
      priority: assertEnum(args.priority, REQUIREMENT_PRIORITIES, 'priority') ?? 'should',
      acceptanceCriteria: parseCriteria(args.ac),
    };
    try {
      const created = await addRequirement(root, requirement, { human: Boolean(args.human), by: args.by });
      ctx.logger.info(`Created ${created.id} (${created.status})`);
      if (ctx.json) printJson(created);
      return 0;
    } catch (error) {
      ctx.logger.error(error.message);
      return 1;
    }
  }

  if (sub === 'update') {
    const id = args._[1];
    if (!id) {
      ctx.logger.error('Usage: aidf requirements update <id> [--status S] [--title t] [--description d] [--priority P]');
      return 1;
    }
    const updates = {};
    if (args.status) updates.status = assertEnum(args.status, REQUIREMENT_STATUSES, 'status');
    if (args.title) updates.title = args.title;
    if (args.description !== undefined) updates.description = args.description;
    if (args.priority) updates.priority = assertEnum(args.priority, REQUIREMENT_PRIORITIES, 'priority');
    if (Object.keys(updates).length === 0) {
      ctx.logger.error('Nothing to update. Provide --status, --title, --description or --priority.');
      return 1;
    }
    try {
      const updated = await updateRequirement(root, id, updates);
      ctx.logger.info(`Updated ${updated.id}`);
      if (ctx.json) printJson(updated);
      return 0;
    } catch (error) {
      ctx.logger.error(error.message);
      return 1;
    }
  }

  if (sub === 'delete') {
    const id = args._[1];
    if (!id) {
      ctx.logger.error('Usage: aidf requirements delete <id> (marks the requirement BLOCKED; it is never removed)');
      return 1;
    }
    try {
      const req = await deleteRequirement(root, id);
      ctx.logger.info(`${req.id} marked BLOCKED (soft delete; use update --status to restore)`);
      return 0;
    } catch (error) {
      ctx.logger.error(error.message);
      return 1;
    }
  }

  if (sub === 'coverage') {
    const result = await calculateCoverage(root);
    if (ctx.json) {
      printJson(result);
    } else {
      ctx.logger.info(
        `Coverage: ${result.coverage.covered}/${result.coverage.total} covered, `
        + `${result.coverage.untested} untested, ${result.coverage.testedButNotPassing} tested-but-failing`,
      );
      ctx.logger.table(
        result.details.map((d) => ({
          id: d.id,
          status: d.status,
          type: d.type,
          criteria: d.tests,
          covered: d.covered ? 'YES' : 'NO',
        })),
        [
          { key: 'id', label: 'ID' },
          { key: 'status', label: 'Status' },
          { key: 'type', label: 'Type' },
          { key: 'criteria', label: 'Criteria' },
          { key: 'covered', label: 'Covered' },
        ],
      );
    }
    return 0;
  }

  ctx.logger.error(`Unknown requirements subcommand: ${sub}`);
  return 1;
}
