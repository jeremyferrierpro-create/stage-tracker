import path from 'node:path';
import fsp from 'node:fs/promises';
import { parseArgs } from '../util/args.mjs';
import { resolveProjectRoot, isInitialized, aidfPaths } from '../core/paths.mjs';
import { detectEnvironment, detectProject } from '../core/detect.mjs';
import { ensureDir, writeJson, writeAtomic } from '../util/fsx.mjs';
import { recordEvidence } from '../core/evidence.mjs';
import { attachLogFile } from '../util/logger.mjs';

export function scanArgSpec() {
  return {
    booleans: ['json', 'record', 'help'],
    aliases: { h: 'help', t: 'target' },
  };
}

export async function scanCommand(argv, ctx) {
  const args = parseArgs(argv, scanArgSpec());
  if (args.help) {
    ctx.logger.info('Usage: aidf scan [--target <dir>] [--record] [--json]');
    return 0;
  }
  const root = resolveProjectRoot({ target: args.target, cwd: process.cwd() });
  if (!isInitialized(root)) {
    ctx.logger.error(`AIDF is not initialized in ${root}. Run "aidf install" first.`);
    return 1;
  }
  await attachLogFile(ctx.logger, root).catch(() => null);
  const env = await detectEnvironment();
  const project = await detectProject(root);
  const scan = {
    schemaVersion: 1,
    generatedAt: new Date().toISOString(),
    root,
    environment: env,
    project,
    secretFiles: await listSecretFileNames(root),
  };
  const paths = aidfPaths(root);
  await ensureDir(paths.reports);
  await writeJson(path.join(paths.reports, 'scan.json'), scan);
  await writeAtomic(path.join(paths.reports, 'SCAN.md'), renderScan(scan));
  let evidence = null;
  if (args.record) {
    evidence = await recordEvidence(root, {
      type: 'project-scan',
      status: 'PASS',
      summary: `Project scan: ${project.languages.map((l) => l.language).join(', ') || 'unknown stack'}; ${project.tests.runners.join(', ') || 'no test runner detected'}.`,
      createdBy: 'aidf',
      files: [...new Set([
        ...project.languages.map((l) => l.marker),
        ...(project.packageJson ? ['package.json'] : []),
      ])],
      checks: [
        { id: 'languages', status: project.languages.length ? 'PASS' : 'NOT_PROVEN', reason: project.languages.map((l) => l.marker).join(', ') },
        { id: 'tests', status: project.tests.runners.length ? 'PASS' : 'NOT_PROVEN', reason: project.tests.runners.join(', ') },
        { id: 'secrets', status: scan.secretFiles.length ? 'BLOCKED' : 'PASS', reason: scan.secretFiles.length ? `${scan.secretFiles.length} secret file(s) present; content not read.` : 'No secret files detected at the scanned depth.' },
      ],
      limitations: [
        'Detection is based on known marker files and manifests; absence is not proof that a technology is unused.',
        'Secret detection reports file names only and is not exhaustive.',
      ],
    });
  }
  if (ctx.json) {
    process.stdout.write(`${JSON.stringify({ scan, evidence }, null, 2)}\n`);
  } else {
    ctx.logger.info(`Project: ${root}`);
    ctx.logger.info(`Languages: ${project.languages.map((l) => `${l.language} (${l.marker})`).join(', ') || 'none detected'}`);
    ctx.logger.info(`Frameworks: ${project.frameworks.join(', ') || 'none detected'}`);
    ctx.logger.info(`Test runners: ${project.tests.runners.join(', ') || 'none detected'}`);
    ctx.logger.info(`Git: ${project.git.repository ? `${project.git.branch} @ ${project.git.head}${project.git.dirty ? ` (${project.git.dirty} changed)` : ''}` : 'not a git repository'}`);
    ctx.logger.info(`AI config: ${Object.entries(project.aiConfig).filter(([, v]) => v).map(([k]) => k).join(', ') || 'none'}`);
    if (scan.secretFiles.length > 0) {
      ctx.logger.warn(`Secret-like files present (content NOT read): ${scan.secretFiles.join(', ')}`);
    }
    if (evidence) ctx.logger.info(`Evidence recorded: ${evidence.file}`);
    ctx.logger.info('Report: .aidf/reports/SCAN.md');
  }
  return 0;
}

async function listSecretFileNames(root) {
  const names = [];
  const candidates = ['.env', '.env.local', '.env.production', '.env.development', 'id_rsa', 'id_ed25519', '.npmrc', '.pypirc'];
  for (const name of candidates) {
    try {
      await fsp.access(path.join(root, name));
      names.push(name);
    } catch {
      // absent
    }
  }
  return names;
}

function renderScan(scan) {
  const p = scan.project;
  const lines = [];
  lines.push('# AIDF Project Scan');
  lines.push('');
  lines.push(`- Root: \`${scan.root}\``);
  lines.push(`- Generated: ${scan.generatedAt}`);
  lines.push(`- OS: ${scan.environment.os.platform} ${scan.environment.os.release} (${scan.environment.os.arch})`);
  lines.push(`- Node: ${scan.environment.node.version}`);
  lines.push('');
  lines.push('## Stack');
  lines.push('');
  lines.push('| Language | Ecosystem | Evidence |');
  lines.push('|---|---|---|');
  for (const lang of p.languages) lines.push(`| ${lang.language} | ${lang.ecosystem} | \`${lang.marker}\` |`);
  if (p.languages.length === 0) lines.push('| _none detected_ | - | - |');
  lines.push('');
  lines.push(`Frameworks: ${p.frameworks.join(', ') || '_none detected_'}`);
  lines.push('');
  lines.push('## Tests');
  lines.push('');
  lines.push(`- Runners: ${p.tests.runners.join(', ') || '_none detected_'}`);
  lines.push(`- Directories: ${p.tests.directories.join(', ') || '_none detected_'}`);
  lines.push(`- Scripts: ${Object.keys(p.tests.scripts).join(', ') || '_none_'}`);
  lines.push('');
  lines.push('## Git');
  lines.push('');
  lines.push(p.git.repository
    ? `- Branch: ${p.git.branch}\n- HEAD: ${p.git.head}\n- Changed files: ${p.git.dirty}`
    : '- Not a git repository.');
  lines.push('');
  lines.push('## Existing AI configuration');
  lines.push('');
  for (const [key, value] of Object.entries(p.aiConfig)) lines.push(`- ${key}: ${value ? 'PRESENT' : 'ABSENT'}`);
  lines.push('');
  lines.push('## Secret-like files (names only)');
  lines.push('');
  lines.push(scan.secretFiles.length ? scan.secretFiles.map((f) => `- \`${f}\``).join('\n') : '_None detected._');
  lines.push('');
  lines.push('> Detection is not exhaustive and absence is not proof. No secret content was read.');
  lines.push('');
  return `${lines.join('\n')}\n`;
}
