import path from 'node:path';
import os from 'node:os';
import fsp from 'node:fs/promises';
import { parseYaml, dumpYaml } from '../util/yaml.mjs';
import { splitFrontmatter } from '../util/frontmatter.mjs';
import { readText, readDirSafe, exists, sha256File, sha256Text, writeAtomic, toPosix, resolveInside } from '../util/fsx.mjs';
import { aidfPaths, FRAMEWORK_ROOT } from './paths.mjs';

export const SKILL_STATUSES = [
  'DISCOVERED', 'VALIDATED', 'REGISTERED', 'AVAILABLE', 'BLOCKED', 'INCOMPATIBLE',
];

const NAME_RE = /^[a-z0-9][a-z0-9-]{0,63}$/;

export async function loadSkillCatalog(frameworkRoot = FRAMEWORK_ROOT) {
  const file = path.join(frameworkRoot, 'assets', 'registry', 'skills.yaml');
  const parsed = parseYaml(await readText(file));
  return parsed?.skills ?? [];
}

export async function loadDependencies(frameworkRoot = FRAMEWORK_ROOT) {
  const file = path.join(frameworkRoot, 'assets', 'registry', 'dependencies.yaml');
  const parsed = parseYaml(await readText(file));
  return parsed?.skills ?? {};
}

export async function loadActivationMap(frameworkRoot = FRAMEWORK_ROOT) {
  const file = path.join(frameworkRoot, 'assets', 'registry', 'activation-map.yaml');
  return parseYaml(await readText(file));
}

export function validateSkillContent(content, options = {}) {
  const errors = [];
  const warnings = [];
  const { frontmatter, body } = splitFrontmatter(content);
  if (!frontmatter) {
    errors.push('Missing YAML frontmatter (--- ... ---).');
    return { valid: false, errors, warnings, frontmatter: null, body };
  }
  const name = frontmatter.name;
  const description = frontmatter.description;
  if (typeof name !== 'string' || !NAME_RE.test(name)) {
    errors.push('Frontmatter "name" must match ^[a-z0-9][a-z0-9-]{0,63}$.');
  }
  if (typeof description !== 'string' || description.trim() === '') {
    errors.push('Frontmatter "description" is required.');
  } else if (description.length > 1024) {
    errors.push(`Frontmatter "description" is ${description.length} chars (max 1024).`);
  }
  if (typeof body !== 'string' || body.trim().length < 20) {
    warnings.push('Skill body is very short; instructions may be insufficient.');
  }
  if (options.directoryName && name && options.directoryName !== name) {
    warnings.push(`Directory name "${options.directoryName}" does not match skill name "${name}".`);
  }
  return {
    valid: errors.length === 0,
    errors,
    warnings,
    frontmatter,
    body,
    name,
    description,
    aidf: frontmatter?.metadata?.aidf ?? null,
  };
}

export async function discoverSkills(roots) {
  const discovered = [];
  const seenPaths = new Set();
  for (const root of roots) {
    if (!root || !(await exists(root))) continue;
    const stack = [{ dir: root, depth: 0 }];
    while (stack.length > 0) {
      const { dir, depth } = stack.pop();
      if (depth > 4) continue;
      const entries = await readDirSafe(dir);
      for (const entry of entries) {
        if (entry.name === 'node_modules' || entry.name === '.git') continue;
        const full = path.join(dir, entry.name);
        if (entry.isDirectory()) {
          stack.push({ dir: full, depth: depth + 1 });
        } else if (entry.isFile() && entry.name === 'SKILL.md') {
          const posix = toPosix(full);
          if (seenPaths.has(posix)) continue;
          seenPaths.add(posix);
          try {
            const content = await readText(full);
            const validation = validateSkillContent(content, { directoryName: path.basename(dir) });
            discovered.push({
              path: posix,
              root: toPosix(root),
              name: validation.name ?? path.basename(dir),
              description: validation.description ?? '',
              aidf: validation.aidf,
              errors: validation.errors,
              warnings: validation.warnings,
              valid: validation.valid,
              hash: await sha256File(full),
              size: Buffer.byteLength(content, 'utf8'),
            });
          } catch (error) {
            discovered.push({
              path: posix,
              root: toPosix(root),
              name: path.basename(dir),
              errors: [`Cannot read skill: ${error.message}`],
              warnings: [],
              valid: false,
            });
          }
        }
      }
    }
  }
  const byName = new Map();
  for (const skill of discovered) {
    const list = byName.get(skill.name) ?? [];
    list.push(skill);
    byName.set(skill.name, list);
  }
  for (const [name, list] of byName) {
    if (list.length > 1) {
      for (const skill of list) {
        skill.warnings.push(`Duplicate skill name "${name}" found in ${list.length} locations.`);
        skill.duplicate = true;
      }
    }
  }
  return discovered;
}

export function defaultSkillRoots(root, frameworkRoot = FRAMEWORK_ROOT) {
  const parent = path.dirname(frameworkRoot);
  return [
    path.join(root, '.qoder', 'skills'),
    path.join(root, '.opencode', 'skills'),
    path.join(root, '.aidf', 'skills'),
    path.join(root, '.claude', 'skills'),
    path.join(root, '.agents', 'skills'),
    path.join(root, '.agent', 'skills'),
    path.join(root, 'skills', 'skills'),
    path.join(os.homedir(), '.qoder', 'skills'),
    path.join(parent, 'skills', 'skills'),
    path.join(parent, 'superpowers', 'skills'),
  ];
}

export async function loadProjectSkillRegistry(root) {
  const file = path.join(aidfPaths(root).registry, 'skills.yaml');
  if (!(await exists(file))) {
    return { schemaVersion: 1, updatedAt: null, skills: [] };
  }
  return parseYaml(await readText(file)) ?? { schemaVersion: 1, skills: [] };
}

export async function saveProjectSkillRegistry(root, registry) {
  const file = path.join(aidfPaths(root).registry, 'skills.yaml');
  registry.updatedAt = new Date().toISOString();
  await writeAtomic(file, dumpYaml(registry));
}

export async function buildSkillRegistry(root, options = {}) {
  const frameworkRoot = options.frameworkRoot ?? FRAMEWORK_ROOT;
  const catalog = await loadSkillCatalog(frameworkRoot);
  const deps = await loadDependencies(frameworkRoot);
  const roots = [...defaultSkillRoots(root, frameworkRoot), ...(options.extraRoots ?? [])];
  const discovered = await discoverSkills(roots);
  const entries = [];
  const adapter = options.adapter ?? 'qoder';

  for (const catalogSkill of catalog) {
    const name = catalogSkill.name;
    const candidates = discovered.filter((s) => s.name === name);
    const compatible = (catalogSkill.ide ?? ['generic']).includes(adapter)
      || (catalogSkill.ide ?? []).includes('generic');
    let base;
    if (candidates.length === 0) {
      base = {
        status: 'BLOCKED',
        path: null,
        hash: null,
        errors: ['Skill file is not installed for any adapter.'],
        warnings: [],
      };
    } else {
      const chosen = candidates[0];
      let status = chosen.valid ? 'VALIDATED' : 'INCOMPATIBLE';
      if (status === 'VALIDATED' && !compatible) status = 'INCOMPATIBLE';
      if (status === 'VALIDATED' && chosen.duplicate) status = 'BLOCKED';
      base = {
        status,
        path: chosen.path,
        hash: chosen.hash,
        errors: chosen.errors,
        warnings: chosen.warnings,
      };
    }
    entries.push({
      id: catalogSkill.id,
      name,
      version: catalogSkill.version,
      source: 'builtin',
      description: catalogSkill.description,
      phases: catalogSkill.phases ?? [],
      documents: catalogSkill.documents ?? [],
      triggers: catalogSkill.triggers ?? [],
      ide: catalogSkill.ide ?? [],
      dependencies: deps[catalogSkill.id]?.requires ?? [],
      _baseStatus: base.status,
      ...base,
    });
  }

  // Resolve dependencies independently of catalog order.
  const byId = new Map(entries.map((e) => [e.id, e]));
  for (let pass = 0; pass < entries.length + 1; pass += 1) {
    let changed = false;
    for (const entry of entries) {
      if (entry._baseStatus !== 'VALIDATED') continue;
      const required = entry.dependencies ?? [];
      if (required.length === 0) {
        if (entry.status !== 'AVAILABLE') {
          entry.status = 'AVAILABLE';
          changed = true;
        }
        continue;
      }
      const depStatuses = required.map((dep) => byId.get(dep)?.status ?? 'MISSING');
      const allReady = depStatuses.every((s) => s === 'AVAILABLE');
      const next = allReady ? 'AVAILABLE' : 'BLOCKED';
      if (entry.status !== next) {
        entry.status = next;
        if (!allReady) {
          entry.errors = [
            ...(entry.errors ?? []).filter((e) => !e.startsWith('Dependencies not ready')),
            `Dependencies not ready: ${required.filter((dep, i) => depStatuses[i] !== 'AVAILABLE').join(', ')}`,
          ];
        }
        changed = true;
      }
    }
    if (!changed) break;
  }

  const external = discovered
    .filter((s) => !catalog.some((c) => c.name === s.name))
    .map((s) => {
      const inQoder = toPosix(s.path).includes('/.qoder/skills/');
      return {
        id: s.aidf?.id ?? s.name,
        name: s.name,
        version: s.aidf?.version ?? 'unknown',
        status: s.valid ? (s.duplicate ? 'BLOCKED' : inQoder ? 'AVAILABLE' : 'VALIDATED') : 'INCOMPATIBLE',
        source: 'external',
        path: s.path,
        description: s.description,
        phases: s.aidf?.phases ?? [],
        documents: s.aidf?.documents ?? [],
        triggers: s.aidf?.triggers ?? [],
        ide: ['qoder', 'generic'],
        hash: s.hash,
        errors: s.errors,
        warnings: s.warnings,
      };
    });

  return {
    schemaVersion: 1,
    updatedAt: new Date().toISOString(),
    adapter,
    skills: [...entries, ...external].map(({ _baseStatus, ...rest }) => rest),
    summary: summarizeSkills([...entries, ...external]),
  };
}

export function summarizeSkills(skills) {
  const summary = {};
  for (const status of SKILL_STATUSES) summary[status] = 0;
  for (const skill of skills) summary[skill.status] = (summary[skill.status] ?? 0) + 1;
  summary.total = skills.length;
  return summary;
}

export function findDependencyCycles(deps) {
  const cycles = [];
  const visiting = new Set();
  const visited = new Set();
  const stack = [];
  function visit(node) {
    if (visiting.has(node)) {
      const index = stack.indexOf(node);
      cycles.push([...stack.slice(index), node]);
      return;
    }
    if (visited.has(node)) return;
    visiting.add(node);
    stack.push(node);
    for (const dep of deps[node]?.requires ?? []) visit(dep);
    stack.pop();
    visiting.delete(node);
    visited.add(node);
  }
  for (const node of Object.keys(deps)) visit(node);
  return cycles;
}

export async function validateRegistry(root, registry, deps) {
  const errors = [];
  const warnings = [];
  const cycles = findDependencyCycles(deps);
  for (const cycle of cycles) errors.push(`Dependency cycle: ${cycle.join(' -> ')}`);
  const byId = new Map(registry.skills.map((s) => [s.id, s]));
  for (const skill of registry.skills) {
    if (skill.source !== 'builtin') continue;
    if (skill.status === 'INCOMPATIBLE' || skill.status === 'BLOCKED') {
      errors.push(`${skill.id}: ${skill.status} (${(skill.errors ?? []).join('; ') || 'dependency or compatibility issue'})`);
    }
    for (const dep of deps[skill.id]?.requires ?? []) {
      if (!byId.has(dep)) errors.push(`${skill.id}: missing dependency "${dep}"`);
    }
  }
  for (const skill of registry.skills) {
    if (skill.duplicate) warnings.push(`${skill.name}: duplicate name`);
  }
  return { valid: errors.length === 0, errors, warnings, cycles };
}

export async function linkSkill(root, registry, skillId, options = {}) {
  const skill = registry.skills.find((s) => s.id === skillId || s.name === skillId);
  if (!skill) throw new Error(`Unknown skill: ${skillId}`);
  if (!skill.path) throw new Error(`Skill "${skillId}" has no discovered path.`);
  if (skill.source === 'builtin') {
    throw new Error('Built-in skills are installed by the adapter; no link is required.');
  }
  const target = path.resolve(skill.path, '..');
  const adapter = options.adapter ?? 'qoder';
  const linkPath = adapter === 'opencode'
    ? path.join('.opencode', 'skills', skill.name)
    : path.join('.qoder', 'skills', skill.name);
  const abs = resolveInside(root, linkPath);
  if (await exists(abs)) {
    return { status: 'UNCHANGED', path: linkPath, target: toPosix(target) };
  }
  if (options.dryRun) return { status: 'WOULD_LINK', path: linkPath, target: toPosix(target) };
  await fsp.mkdir(path.dirname(abs), { recursive: true });
  await fsp.symlink(target, abs, process.platform === 'win32' ? 'junction' : 'dir');
  return { status: 'LINKED', path: linkPath, target: toPosix(target) };
}

export function hashSkillContent(content) {
  return sha256Text(content);
}
