import { parseArgs } from '../util/args.mjs';
import { resolveProjectRoot, isInitialized } from '../core/paths.mjs';
import { attachLogFile } from '../util/logger.mjs';
import { toPosix } from '../util/fsx.mjs';
import {
  generateTestPlan, saveTestPlan, loadTestPlan, testCoverage,
} from '../core/testplan.mjs';
import { validateArtefact } from '../core/artefacts/index.mjs';

export function testsArgSpec() {
  return {
    booleans: ['json', 'help', 'write', 'all'],
    aliases: { h: 'help', t: 'target', type: 'type' },
  };
}

export async function testsCommand(argv, ctx) {
  const args = parseArgs(argv, testsArgSpec());
  const sub = args._[0] ?? 'plan';
  if (args.help || sub === 'help') {
    ctx.logger.info('Usage: aidf tests <plan|coverage> [--write] [--type <unit|integration|api|e2e|security|accessibility|performance>] [--json]');
    return 0;
  }
  const root = resolveProjectRoot({ target: args.target, cwd: process.cwd() });
  if (!isInitialized(root)) {
    ctx.logger.error(`AIDF is not initialized in ${root}. Run "aidf install" first.`);
    return 1;
  }
  await attachLogFile(ctx.logger, root).catch(() => null);

  if (sub === 'coverage') {
    const coverage = await testCoverage(root);
    if (ctx.json) {
      process.stdout.write(`${JSON.stringify(coverage, null, 2)}\n`);
    } else {
      ctx.logger.info(
        `Test coverage: ${coverage.covered}/${coverage.total} requirement(s) covered`
        + (coverage.testPlan ? ` (plan ${coverage.testPlan})` : ' (no test plan)'),
      );
      for (const d of coverage.details) {
        ctx.logger.info(`  ${d.covered ? 'COVERED  ' : 'UNCOVERED'} ${d.requirement} — ${d.acceptanceCriteria} criterion/criteria, ${d.plannedCases.length} planned case(s)`);
      }
      if (coverage.uncovered.length > 0) {
        ctx.logger.warn(`${coverage.uncovered.length} requirement(s) without any planned or artefact test.`);
      }
    }
    return 0;
  }

  if (sub !== 'plan') {
    ctx.logger.error(`Unknown tests subcommand: ${sub}`);
    return 1;
  }

  const plan = await generateTestPlan(root, { type: args.type ?? null });
  const validation = await validateArtefact(plan);
  if (!validation.valid) {
    ctx.logger.error(`Generated test plan failed validation: ${validation.errors.join('; ')}`);
    return 1;
  }

  let artefactPath = null;
  if (args.write) {
    artefactPath = await saveTestPlan(root, plan);
  }

  if (ctx.json) {
    process.stdout.write(`${JSON.stringify({ testPlan: plan, artefact: artefactPath ? toPosix(artefactPath) : null }, null, 2)}\n`);
    return 0;
  }

  ctx.logger.info(`Test plan ${plan.id} — ${plan.testCases.length} planned case(s) covering ${plan.links.covers.length} requirement(s)`);
  for (const tc of plan.testCases.slice(0, 20)) {
    ctx.logger.info(`  ${tc.id} [${tc.type}/${tc.status}] ${tc.name}`);
  }
  if (plan.testCases.length > 20) ctx.logger.info(`  … ${plan.testCases.length - 20} more`);
  for (const w of plan.validation.warnings) ctx.logger.warn(`warning: ${w}`);
  if (artefactPath) ctx.logger.info(`Artefact: ${toPosix(artefactPath)}`);
  else ctx.logger.info('Not persisted. Re-run with --write to store .aidf/artefacts/test-plan.json.');
  return 0;
}
