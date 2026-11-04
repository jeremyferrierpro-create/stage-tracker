import os from 'node:os';
import path from 'node:path';
import { execFile } from 'node:child_process';
import { promisify } from 'node:util';
import { existsSync } from '../util/fsx.mjs';
import { readJson } from '../util/fsx.mjs';

const execFileAsync = promisify(execFile);

export async function runTool(command, args = [], options = {}) {
  const { cwd = process.cwd(), timeout = 5000 } = options;
  try {
    const { stdout, stderr } = await execFileAsync(command, args, {
      cwd,
      timeout,
      windowsHide: true,
      maxBuffer: 1024 * 1024,
      shell: false,
    });
    return { ok: true, stdout: stdout.trim(), stderr: stderr.trim() };
  } catch (error) {
    return {
      ok: false,
      stdout: (error.stdout ?? '').toString().trim(),
      stderr: (error.stderr ?? '').toString().trim(),
      code: error.code,
      message: error.message,
    };
  }
}

async function which(command) {
  const probe = process.platform === 'win32' ? 'where' : 'which';
  const result = await runTool(probe, [command]);
  return result.ok ? result.stdout.split(/\r?\n/)[0] : null;
}

export async function detectEnvironment() {
  const home = os.homedir();
  const qoderHome = path.join(home, '.qoder');
  const qoderApp = process.platform === 'win32'
    ? path.join(process.env.LOCALAPPDATA ?? '', 'Programs', 'Qoder IDE')
    : null;
  const tools = {};
  for (const tool of ['node', 'npm', 'pnpm', 'yarn', 'bun', 'python', 'uv', 'git', 'gh', 'specify', 'qodercli', 'docker']) {
    tools[tool] = await which(tool);
  }
  const nodeVersion = process.versions.node;
  const specifyVersion = tools.specify ? await runTool(tools.specify, ['--version']) : null;
  return {
    os: {
      platform: process.platform,
      release: os.release(),
      arch: os.arch(),
      version: os.version ? os.version() : '',
    },
    shell: process.env.SHELL ?? process.env.ComSpec ?? 'unknown',
    home,
    tools,
    node: {
      version: nodeVersion,
      execPath: process.execPath,
    },
    ide: {
      qoder: {
        installed: existsSync(qoderHome) || Boolean(qoderApp && existsSync(qoderApp)),
        home: existsSync(qoderHome) ? qoderHome : null,
        app: qoderApp && existsSync(qoderApp) ? qoderApp : null,
        projectRules: '.qoder/rules',
        projectSkills: '.qoder/skills',
        projectAgents: '.qoder/agents',
        projectSettings: '.qoder/settings.json',
        agentsMd: true,
      },
    },
    specKit: {
      cliPath: tools.specify,
      cliVersion: specifyVersion?.ok ? specifyVersion.stdout.split(/\r?\n/)[0] : null,
    },
  };
}

const LANGUAGE_MARKERS = [
  { file: 'package.json', language: 'JavaScript/TypeScript', ecosystem: 'node' },
  { file: 'tsconfig.json', language: 'TypeScript', ecosystem: 'node' },
  { file: 'pyproject.toml', language: 'Python', ecosystem: 'python' },
  { file: 'requirements.txt', language: 'Python', ecosystem: 'python' },
  { file: 'Cargo.toml', language: 'Rust', ecosystem: 'rust' },
  { file: 'go.mod', language: 'Go', ecosystem: 'go' },
  { file: 'pom.xml', language: 'Java', ecosystem: 'java' },
  { file: 'build.gradle', language: 'Java/Kotlin', ecosystem: 'java' },
  { file: 'composer.json', language: 'PHP', ecosystem: 'php' },
  { file: 'Gemfile', language: 'Ruby', ecosystem: 'ruby' },
  { file: '*.csproj', language: 'C#', ecosystem: 'dotnet' },
];

const FRAMEWORK_DEPS = {
  react: 'React',
  'react-dom': 'React',
  next: 'Next.js',
  vue: 'Vue',
  nuxt: 'Nuxt',
  svelte: 'Svelte',
  '@angular/core': 'Angular',
  express: 'Express',
  fastify: 'Fastify',
  nestjs: 'NestJS',
  '@nestjs/core': 'NestJS',
  vite: 'Vite',
  tailwindcss: 'Tailwind CSS',
  prisma: 'Prisma',
  '@prisma/client': 'Prisma',
  drizzle: 'Drizzle ORM',
  'drizzle-orm': 'Drizzle ORM',
  vitest: 'Vitest',
  jest: 'Jest',
  playwright: 'Playwright',
  '@playwright/test': 'Playwright',
  cypress: 'Cypress',
  typescript: 'TypeScript',
  zod: 'Zod',
  trpc: 'tRPC',
  graphql: 'GraphQL',
  electron: 'Electron',
};

export async function detectProject(root) {
  const languages = [];
  for (const marker of LANGUAGE_MARKERS) {
    if (marker.file.includes('*')) continue;
    if (existsSync(path.join(root, marker.file))) {
      languages.push({ language: marker.language, ecosystem: marker.ecosystem, marker: marker.file });
    }
  }

  const packageJson = await readJson(path.join(root, 'package.json'), null);
  const frameworks = [];
  if (packageJson) {
    const deps = { ...(packageJson.dependencies ?? {}), ...(packageJson.devDependencies ?? {}) };
    for (const [dep, label] of Object.entries(FRAMEWORK_DEPS)) {
      if (deps[dep]) frameworks.push(label);
    }
  }

  const tests = {
    packageManager: packageJson ? (existsSync(path.join(root, 'package-lock.json')) ? 'npm'
      : existsSync(path.join(root, 'pnpm-lock.yaml')) ? 'pnpm'
        : existsSync(path.join(root, 'yarn.lock')) ? 'yarn'
          : existsSync(path.join(root, 'bun.lockb')) ? 'bun' : 'unknown') : null,
    scripts: packageJson?.scripts ?? {},
    directories: ['tests', 'test', '__tests__', 'spec', 'e2e'].filter((d) => existsSync(path.join(root, d))),
    files: [],
    runners: [],
  };
  if (packageJson) {
    if (packageJson.devDependencies?.vitest || packageJson.dependencies?.vitest) tests.runners.push('vitest');
    if (packageJson.devDependencies?.jest || packageJson.dependencies?.jest) tests.runners.push('jest');
    if (packageJson.devDependencies?.['@playwright/test']) tests.runners.push('playwright');
    if (packageJson.devDependencies?.mocha) tests.runners.push('mocha');
    if (packageJson.scripts?.test) tests.runners.push('npm:test');
  }
  if (existsSync(path.join(root, 'pytest.ini')) || existsSync(path.join(root, 'conftest.py'))) tests.runners.push('pytest');
  if (existsSync(path.join(root, 'phpunit.xml')) || existsSync(path.join(root, 'phpunit.xml.dist'))) tests.runners.push('phpunit');

  const git = await runTool('git', ['rev-parse', '--is-inside-work-tree'], { cwd: root });
  const gitInfo = {
    repository: git.ok && git.stdout === 'true',
    branch: null,
    head: null,
    dirty: null,
  };
  if (gitInfo.repository) {
    const branch = await runTool('git', ['rev-parse', '--abbrev-ref', 'HEAD'], { cwd: root });
    const head = await runTool('git', ['rev-parse', '--short', 'HEAD'], { cwd: root });
    const status = await runTool('git', ['status', '--porcelain'], { cwd: root });
    gitInfo.branch = branch.ok ? branch.stdout : null;
    gitInfo.head = head.ok ? head.stdout : null;
    gitInfo.dirty = status.ok ? status.stdout.split(/\r?\n/).filter(Boolean).length : null;
  }

  const aiConfig = {
    agentsMd: existsSync(path.join(root, 'AGENTS.md')),
    claudeMd: existsSync(path.join(root, 'CLAUDE.md')),
    qoderRules: existsSync(path.join(root, '.qoder', 'rules')),
    qoderSkills: existsSync(path.join(root, '.qoder', 'skills')),
    qoderSettings: existsSync(path.join(root, '.qoder', 'settings.json')),
    cursorRules: existsSync(path.join(root, '.cursor', 'rules')),
    copilotInstructions: existsSync(path.join(root, '.github', 'copilot-instructions.md')),
    agentDir: existsSync(path.join(root, '.agent')),
    specifyDir: existsSync(path.join(root, '.specify')),
  };

  const docs = {
    readme: existsSync(path.join(root, 'README.md')),
    docsDir: existsSync(path.join(root, 'docs')),
    aidfDir: existsSync(path.join(root, '.aidf')),
  };

  const ci = ['.github/workflows', '.gitlab-ci.yml', 'azure-pipelines.yml', 'Jenkinsfile']
    .filter((p) => existsSync(path.join(root, p)));

  return {
    root,
    languages,
    frameworks: [...new Set(frameworks)],
    packageJson: packageJson
      ? {
          name: packageJson.name ?? null,
          version: packageJson.version ?? null,
          type: packageJson.type ?? null,
          scripts: packageJson.scripts ?? {},
        }
      : null,
    tests,
    git: gitInfo,
    aiConfig,
    docs,
    ci,
  };
}
