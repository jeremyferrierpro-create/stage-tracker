import path from 'node:path';
import { listFiles, toPosix } from '../util/fsx.mjs';

const FRAMEWORK_COPY_DIRS = ['src', 'assets', 'docs/aidf'];
const FRAMEWORK_COPY_FILES = ['package.json', 'README.md', 'LICENSE', 'INSTALLATION.md', 'UTILISATION.md', 'QODER.md', 'CHANGELOG.md'];

export function isSelfHosted(root, frameworkRoot) {
  return path.resolve(frameworkRoot) === path.resolve(root, '.aidf', 'framework');
}

export async function planCore(root, options = {}) {
  const frameworkRoot = path.resolve(options.frameworkRoot);
  const actions = [];
  const selfHosted = isSelfHosted(root, frameworkRoot);

  if (!selfHosted) {
    for (const dir of FRAMEWORK_COPY_DIRS) {
      const files = await listFiles(path.join(frameworkRoot, dir), { ignore: [] });
      for (const rel of files) {
        actions.push({
          kind: 'copy',
          path: path.posix.join('.aidf', 'framework', dir, rel),
          source: path.join(frameworkRoot, dir, rel),
          category: 'framework',
        });
      }
    }
    for (const file of FRAMEWORK_COPY_FILES) {
      actions.push({
        kind: 'copy',
        path: path.posix.join('.aidf', 'framework', file),
        source: path.join(frameworkRoot, file),
        category: 'framework',
      });
    }
  }

  actions.push({
    kind: 'write',
    path: '.aidf/bin/aidf.mjs',
    content: launcherSource(),
    category: 'framework',
  });

  actions.push({
    kind: 'copy',
    path: '.aidf/AIDF.md',
    source: path.join(frameworkRoot, 'assets', 'core', 'AIDF.md'),
    category: 'instructions',
  });

  const docFiles = await listFiles(path.join(frameworkRoot, 'assets', 'docs'));
  for (const rel of docFiles) {
    actions.push({
      kind: 'copy',
      path: path.posix.join('.aidf', 'docs', rel),
      source: path.join(frameworkRoot, 'assets', 'docs', rel),
      category: 'documents',
      frozen: true,
    });
  }

  for (const registry of ['sources.yaml', 'activation-map.yaml', 'dependencies.yaml']) {
    actions.push({
      kind: 'copy',
      path: path.posix.join('.aidf', 'registry', registry),
      source: path.join(frameworkRoot, 'assets', 'registry', registry),
      category: 'registry',
      frozen: true,
    });
  }

  for (const workflow of ['phases.yaml', 'gates.yaml']) {
    actions.push({
      kind: 'copy',
      path: path.posix.join('.aidf', 'workflows', workflow),
      source: path.join(frameworkRoot, 'assets', 'workflows', workflow),
      category: 'workflows',
      frozen: true,
    });
  }

  const knowledgeFiles = await listFiles(path.join(frameworkRoot, 'assets', 'knowledge'));
  for (const rel of knowledgeFiles) {
    actions.push({
      kind: 'copy',
      path: path.posix.join('.aidf', 'knowledge', rel),
      source: path.join(frameworkRoot, 'assets', 'knowledge', rel),
      category: 'knowledge',
      frozen: true,
    });
  }

  actions.push({
    kind: 'write',
    path: '.aidf/.gitignore',
    content: [
      '# AIDF local-only state',
      'cache/',
      'logs/',
      'backups/',
      'state/context/',
      '',
      '# Evidence and documents are intentionally committed.',
      '',
    ].join('\n'),
    category: 'config',
  });

  actions.push({
    kind: 'mkdir',
    path: '.aidf/evidence',
    category: 'directories',
  });
  actions.push({
    kind: 'mkdir',
    path: '.aidf/state/context',
    category: 'directories',
  });
  actions.push({
    kind: 'mkdir',
    path: '.aidf/skills/project-skills',
    category: 'directories',
  });
  actions.push({
    kind: 'mkdir',
    path: '.aidf/reports',
    category: 'directories',
  });

  return { actions, selfHosted };
}

export function launcherSource() {
  return `#!/usr/bin/env node
import { main } from '../framework/src/cli.mjs';

main(process.argv.slice(2)).then((code) => {
  process.exitCode = code;
}).catch((error) => {
  process.stderr.write(\`aidf: fatal: \${error && error.message ? error.message : String(error)}\\n\`);
  process.exitCode = 70;
});
`;
}

export function renderInstallReport({ root, projectName, adapter, results, summary, version }) {
  const lines = [];
  lines.push('# AIDF Installation Report');
  lines.push('');
  lines.push(`- Project: ${projectName}`);
  lines.push(`- Root: \`${toPosix(root)}\``);
  lines.push(`- AIDF version: ${version}`);
  lines.push(`- Adapter: ${adapter}`);
  lines.push(`- Generated: ${new Date().toISOString()}`);
  lines.push('');
  lines.push('## Summary');
  lines.push('');
  lines.push(`- Created: ${summary.created}`);
  lines.push(`- Updated: ${summary.updated}`);
  lines.push(`- Unchanged: ${summary.unchanged}`);
  lines.push(`- Conflicts: ${summary.conflicts}`);
  lines.push(`- Failed: ${summary.failed}`);
  lines.push('');
  lines.push('## Conflicts and warnings');
  lines.push('');
  const conflicts = results.filter((r) => r.status === 'CONFLICT' || r.status === 'FAILED');
  if (conflicts.length === 0) lines.push('_None._');
  for (const c of conflicts) lines.push(`- \`${c.path}\` — ${c.status}: ${c.reason ?? ''}`);
  lines.push('');
  lines.push('## Next steps');
  lines.push('');
  lines.push('1. Run `node .aidf/bin/aidf.mjs doctor`.');
  lines.push('2. Complete `.aidf/docs/constitution.md` and the eight reference documents from real project analysis.');
  lines.push('3. Run `node .aidf/bin/aidf.mjs scan --record` and `node .aidf/bin/aidf.mjs skills validate`.');
  lines.push('4. In Qoder, confirm the AIDF skills are listed with `/skills` and set the AIDF rules to **Always Apply**.');
  lines.push('');
  return `${lines.join('\n')}\n`;
}
