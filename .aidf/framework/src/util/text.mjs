export const AIDF_BLOCK_BEGIN = '<!-- AIDF:BEGIN managed by aidf - do not edit inside this block -->';
export const AIDF_BLOCK_END = '<!-- AIDF:END -->';

export function hasManagedBlock(text) {
  return text.includes(AIDF_BLOCK_BEGIN) && text.includes(AIDF_BLOCK_END);
}

export function replaceManagedBlock(text, content, blockId = 'default') {
  const begin = blockId === 'default'
    ? AIDF_BLOCK_BEGIN
    : `<!-- AIDF:BEGIN ${blockId} managed by aidf - do not edit inside this block -->`;
  const end = blockId === 'default' ? AIDF_BLOCK_END : `<!-- AIDF:END ${blockId} -->`;
  const block = `${begin}\n${content.trim()}\n${end}`;
  const beginIndex = text.indexOf(begin);
  const endIndex = text.indexOf(end);
  if (beginIndex !== -1 && endIndex !== -1 && endIndex > beginIndex) {
    return `${text.slice(0, beginIndex)}${block}${text.slice(endIndex + end.length)}`;
  }
  if (beginIndex !== -1 || endIndex !== -1) {
    throw new Error(`Malformed AIDF managed block "${blockId}" in file.`);
  }
  const separator = text.trim() === '' ? '' : text.endsWith('\n') ? '\n' : '\n\n';
  return `${text}${separator}${block}\n`;
}

export function removeManagedBlock(text, blockId = 'default') {
  const begin = blockId === 'default'
    ? AIDF_BLOCK_BEGIN
    : `<!-- AIDF:BEGIN ${blockId} managed by aidf - do not edit inside this block -->`;
  const end = blockId === 'default' ? AIDF_BLOCK_END : `<!-- AIDF:END ${blockId} -->`;
  const beginIndex = text.indexOf(begin);
  const endIndex = text.indexOf(end);
  if (beginIndex === -1 || endIndex === -1 || endIndex < beginIndex) return text;
  const before = text.slice(0, beginIndex).replace(/\s+$/, '');
  const after = text.slice(endIndex + end.length).replace(/^\s+/, '');
  if (!before) return after ? `${after}\n` : '';
  if (!after) return `${before}\n`;
  return `${before}\n\n${after}\n`;
}

export function slugify(value) {
  return String(value)
    .normalize('NFKD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
    .slice(0, 64) || 'project';
}

export function truncate(text, max) {
  const value = String(text ?? '');
  return value.length <= max ? value : `${value.slice(0, max - 1)}…`;
}

export function indentBlock(text, spaces = 2) {
  const pad = ' '.repeat(spaces);
  return String(text)
    .split('\n')
    .map((line) => (line ? `${pad}${line}` : line))
    .join('\n');
}

export function stripAnsi(text) {
  return String(text).replace(/\u001B\[[0-9;]*m/g, '');
}
