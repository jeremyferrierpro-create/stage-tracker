import { parseYaml, dumpYaml } from './yaml.mjs';

export function splitFrontmatter(text) {
  const source = String(text ?? '').replace(/^\uFEFF/, '').replace(/\r\n?/g, '\n');
  if (!source.startsWith('---\n')) {
    return { frontmatter: null, body: source, raw: '' };
  }
  const end = source.indexOf('\n---', 4);
  if (end === -1) {
    return { frontmatter: null, body: source, raw: '' };
  }
  const raw = source.slice(4, end + 1);
  const bodyStart = source.indexOf('\n', end + 4);
  const body = bodyStart === -1 ? '' : source.slice(bodyStart + 1);
  return { frontmatter: parseYaml(raw), body, raw };
}

export function joinFrontmatter(frontmatter, body) {
  return `---\n${dumpYaml(frontmatter)}---\n\n${String(body).replace(/^\n+/, '')}`;
}

export function updateFrontmatter(text, updater) {
  const { frontmatter, body } = splitFrontmatter(text);
  const next = updater(frontmatter ?? {}) ?? frontmatter ?? {};
  return joinFrontmatter(next, body);
}
