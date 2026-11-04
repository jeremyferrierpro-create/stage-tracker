import path from 'node:path';
import { parseYaml } from '../util/yaml.mjs';
import { readText, exists, writeAtomic, ensureDir, sha256Text } from '../util/fsx.mjs';
import { FRAMEWORK_ROOT } from './paths.mjs';
import { runTool } from './detect.mjs';

export async function loadSpecKitMapping(frameworkRoot = FRAMEWORK_ROOT) {
  const file = path.join(frameworkRoot, 'assets', 'integrations', 'spec-kit', 'mapping.yaml');
  return parseYaml(await readText(file));
}

export async function specKitStatus(root, options = {}) {
  const mapping = await loadSpecKitMapping(options.frameworkRoot);
  const specifyDir = path.join(root, '.specify');
  const present = await exists(specifyDir);
  const constitutionPath = path.join(specifyDir, 'memory', 'constitution.md');
  const constitutionPresent = await exists(constitutionPath);
  const templates = await exists(path.join(specifyDir, 'templates'));
  const qoderSkills = path.join(root, '.qoder', 'skills');
  const integrationSkills = [];
  if (await exists(qoderSkills)) {
    const { readDirSafe } = await import('../util/fsx.mjs');
    const entries = await readDirSafe(qoderSkills);
    for (const entry of entries) {
      if (entry.isDirectory() && /^speckit[.-]/.test(entry.name)) integrationSkills.push(entry.name);
    }
  }
  const cli = await runTool(options.specifyPath ?? 'specify', ['--version']);
  let constitutionSync = 'NOT_APPLICABLE';
  if (constitutionPresent) {
    const text = await readText(constitutionPath);
    const match = /<!-- AIDF:SOURCE-HASH:([a-f0-9]+) -->/.exec(text);
    if (match) {
      const source = await readText(path.join(root, '.aidf', 'docs', 'constitution.md'));
      constitutionSync = sha256Text(source) === match[1] ? 'IN_SYNC' : 'OUTDATED';
    } else {
      constitutionSync = 'EXTERNAL';
    }
  }
  return {
    present,
    specifyDir: present ? '.specify' : null,
    templates,
    constitutionPresent,
    constitutionSync,
    integrationSkills,
    integrationConfigured: integrationSkills.length > 0,
    cli: {
      available: cli.ok,
      path: options.specifyPath ?? 'specify',
      version: cli.ok ? cli.stdout.split(/\r?\n/)[0] : null,
    },
    expectedIntegration: mapping?.specKit?.integration?.key ?? 'qodercli',
    mapping: mapping?.mapping ?? [],
    status: present
      ? (templates ? 'PRESENT' : 'PARTIAL')
      : 'ABSENT',
  };
}

export async function initSpecKit(root, options = {}) {
  const status = await specKitStatus(root, options);
  if (status.present) {
    return { status: 'UNCHANGED', reason: '.specify/ already exists; AIDF does not reinitialize it.' };
  }
  if (!status.cli.available) {
    return { status: 'BLOCKED', reason: 'The `specify` CLI is not installed. Install specify-cli and retry.' };
  }
  const args = ['init', '--here', '--integration', options.integration ?? 'qodercli', '--ignore-agent-tools', '--non-interactive'];
  // `specify init --here` refuses a non-empty directory in --non-interactive
  // mode. An installed project is never empty, so AIDF asks for an explicit
  // opt-in (`--specify-force`) instead of passing --force silently.
  if (!options.dryRun) {
    const { readDirSafe } = await import('../util/fsx.mjs');
    const entries = await readDirSafe(root);
    if (entries.length > 0 && !options.specifyForce) {
      return {
        status: 'BLOCKED',
        reason: 'The project directory is not empty; specify init requires --force to merge. Re-run with --specify-force to explicitly allow Spec Kit to merge into it.',
      };
    }
    if (options.specifyForce) args.push('--force');
  }
  if (options.dryRun) {
    return { status: 'WOULD_RUN', command: `specify ${args.join(' ')}` };
  }
  if (!options.allowExec) {
    return { status: 'BLOCKED', reason: 'Running the Spec Kit initializer requires --allow-exec.' };
  }
  const result = await runTool(options.specifyPath ?? 'specify', args, { cwd: root, timeout: 180000 });
  if (!result.ok) {
    return { status: 'FAILED', reason: result.stderr || result.message, command: `specify ${args.join(' ')}` };
  }
  return { status: 'CREATED', command: `specify ${args.join(' ')}`, output: result.stdout };
}

export async function syncSpecKit(root, options = {}) {
  const status = await specKitStatus(root, options);
  if (!status.present) {
    return { status: 'NOT_APPLICABLE', reason: '.specify/ does not exist; run `aidf speckit init` first.' };
  }
  const sourcePath = path.join(root, '.aidf', 'docs', 'constitution.md');
  const source = await readText(sourcePath);
  const hash = sha256Text(source);
  const targetPath = path.join(root, '.specify', 'memory', 'constitution.md');
  const targetExists = await exists(targetPath);
  const results = [];

  if (targetExists) {
    const target = await readText(targetPath);
    const match = /<!-- AIDF:SOURCE-HASH:([a-f0-9]+) -->/.exec(target);
    if (!match) {
      results.push({
        status: 'CONFLICT',
        path: '.specify/memory/constitution.md',
        reason: 'Spec Kit already has its own constitution. AIDF will not overwrite it; merge manually or move it aside.',
      });
    } else if (match[1] === hash) {
      results.push({ status: 'UNCHANGED', path: '.specify/memory/constitution.md' });
    } else if (options.dryRun) {
      results.push({ status: 'WOULD_UPDATE', path: '.specify/memory/constitution.md' });
    } else {
      await writeAtomic(targetPath, generatedConstitution(source, hash));
      results.push({ status: 'UPDATED', path: '.specify/memory/constitution.md' });
    }
  } else if (options.adopt) {
    if (options.dryRun) {
      results.push({ status: 'WOULD_CREATE', path: '.specify/memory/constitution.md' });
    } else {
      await ensureDir(path.dirname(targetPath));
      await writeAtomic(targetPath, generatedConstitution(source, hash));
      results.push({ status: 'CREATED', path: '.specify/memory/constitution.md' });
    }
  } else {
    results.push({
      status: 'NOT_INSTALLED',
      path: '.specify/memory/constitution.md',
      reason: 'No Spec Kit constitution exists. Re-run with --adopt to create a generated copy.',
    });
  }

  // The mapping document is AIDF-owned: it is recreated when the AIDF
  // constitution hash changes, and left untouched otherwise.
  const mappingPath = path.join(root, '.specify', 'aidf', 'mapping.md');
  if (await exists(mappingPath)) {
    const currentMapping = await readText(mappingPath);
    if (currentMapping.includes(`AIDF constitution hash: \`${hash}\``)) {
      results.push({ status: 'UNCHANGED', path: '.specify/aidf/mapping.md' });
    } else if (options.dryRun) {
      results.push({ status: 'WOULD_WRITE', path: '.specify/aidf/mapping.md' });
    } else {
      await ensureDir(path.dirname(mappingPath));
      await writeAtomic(mappingPath, renderMapping(status.mapping, hash));
      results.push({ status: 'UPDATED', path: '.specify/aidf/mapping.md' });
    }
  } else if (options.dryRun) {
    results.push({ status: 'WOULD_WRITE', path: '.specify/aidf/mapping.md' });
  } else {
    await ensureDir(path.dirname(mappingPath));
    await writeAtomic(mappingPath, renderMapping(status.mapping, hash));
    results.push({ status: 'CREATED', path: '.specify/aidf/mapping.md' });
  }
  return { status: results.some((r) => r.status === 'CONFLICT') ? 'CONFLICT' : 'OK', results, hash };
}

function generatedConstitution(source, hash) {
  const body = source.replace(/^---[\s\S]*?---\n/, '');
  return `<!-- AIDF:SOURCE-HASH:${hash} -->
<!-- Generated by AIDF from .aidf/docs/constitution.md. Do not edit this copy; edit the source and run \`aidf speckit sync\`. -->

${body.trim()}
`;
}

function renderMapping(mapping, hash) {
  const lines = [];
  lines.push('# AIDF ↔ Spec Kit Mapping');
  lines.push('');
  lines.push(`- Generated: ${new Date().toISOString()}`);
  lines.push(`- AIDF constitution hash: \`${hash}\``);
  lines.push('');
  lines.push('AIDF owns product-wide durable truth. Spec Kit owns feature-level change artefacts.');
  lines.push('');
  lines.push('| AIDF | AIDF path | Spec Kit | Spec Kit path | Owner |');
  lines.push('|---|---|---|---|---|');
  for (const row of mapping) {
    lines.push(`| ${row.aidf} | \`${row.aidfPath}\` | ${row.specKit} | \`${row.specKitPath}\` | ${row.owner} |`);
  }
  lines.push('');
  lines.push('## Rule');
  lines.push('');
  lines.push('Do not copy a fact into both places. A feature spec references PRD requirement IDs; it does not restate the PRD.');
  lines.push('');
  return `${lines.join('\n')}\n`;
}
