/**
 * Minimal YAML subset parser/dumper used by AIDF.
 *
 * Supported: nested maps, sequences (scalars and maps), inline arrays,
 * quoted strings, booleans, null, numbers, block scalars (| and >).
 * Not supported: anchors, aliases, tags, multi-document streams, flow maps.
 * AIDF registries are written by AIDF itself; this parser is deliberately
 * small and tested rather than a general-purpose YAML implementation.
 */

class YamlError extends Error {
  constructor(message, line) {
    super(`${message}${line ? ` (line ${line})` : ''}`);
    this.name = 'YamlError';
    this.line = line;
  }
}

function indentOf(line) {
  const match = /^ */.exec(line);
  return match ? match[0].length : 0;
}

function isBlank(line) {
  return line.trim() === '';
}

function isComment(line) {
  return line.trimStart().startsWith('#');
}

function stripInlineComment(text) {
  let inSingle = false;
  let inDouble = false;
  for (let i = 0; i < text.length; i += 1) {
    const ch = text[i];
    if (ch === "'" && !inDouble) inSingle = !inSingle;
    else if (ch === '"' && !inSingle) inDouble = !inDouble;
    else if (ch === '#' && !inSingle && !inDouble && (i === 0 || /\s/.test(text[i - 1]))) {
      return text.slice(0, i).trimEnd();
    }
  }
  return text.trimEnd();
}

function splitKey(text) {
  let inSingle = false;
  let inDouble = false;
  let depth = 0;
  for (let i = 0; i < text.length; i += 1) {
    const ch = text[i];
    if (ch === "'" && !inDouble) inSingle = !inSingle;
    else if (ch === '"' && !inSingle) inDouble = !inDouble;
    else if (!inSingle && !inDouble) {
      if (ch === '[' || ch === '{') depth += 1;
      else if (ch === ']' || ch === '}') depth -= 1;
      else if (ch === ':' && depth === 0) {
        const next = text[i + 1];
        if (next === undefined || next === ' ' || next === '\t') {
          const rawKey = text.slice(0, i).trim();
          const key = unquote(rawKey);
          const rest = text.slice(i + 1).trim();
          return { key, rest };
        }
      }
    }
  }
  return null;
}

function unquote(value) {
  if (value.length >= 2) {
    if (value.startsWith('"') && value.endsWith('"')) {
      try {
        return JSON.parse(value);
      } catch {
        return value.slice(1, -1);
      }
    }
    if (value.startsWith("'") && value.endsWith("'")) {
      return value.slice(1, -1).replace(/''/g, "'");
    }
  }
  return value;
}

function splitFlow(text) {
  const parts = [];
  let current = '';
  let inSingle = false;
  let inDouble = false;
  let depth = 0;
  for (const ch of text) {
    if (ch === "'" && !inDouble) inSingle = !inSingle;
    else if (ch === '"' && !inSingle) inDouble = !inDouble;
    if (!inSingle && !inDouble) {
      if (ch === '[' || ch === '{') depth += 1;
      if (ch === ']' || ch === '}') depth -= 1;
      if (ch === ',' && depth === 0) {
        parts.push(current.trim());
        current = '';
        continue;
      }
    }
    current += ch;
  }
  if (current.trim() !== '' || parts.length > 0) parts.push(current.trim());
  return parts.filter((p) => p !== '');
}

function parseScalar(raw) {
  let text = stripInlineComment(raw).trim();
  if (text === '') return '';
  if (text === '~' || text.toLowerCase() === 'null') return null;
  if (text.toLowerCase() === 'true') return true;
  if (text.toLowerCase() === 'false') return false;
  if (text.startsWith('[') && text.endsWith(']')) {
    return splitFlow(text.slice(1, -1)).map((item) => parseScalar(item));
  }
  if (text.startsWith('{') && text.endsWith('}')) {
    const inner = text.slice(1, -1).trim();
    if (inner === '') return {};
    const obj = {};
    for (const part of splitFlow(inner)) {
      const kv = splitKey(part);
      if (!kv) throw new YamlError(`Invalid inline map entry: ${part}`);
      obj[kv.key] = parseScalar(kv.rest);
    }
    return obj;
  }
  if (/^-?\d+$/.test(text)) return Number.parseInt(text, 10);
  if (/^-?\d+\.\d+$/.test(text)) return Number.parseFloat(text);
  if (
    (text.startsWith('"') && text.endsWith('"')) ||
    (text.startsWith("'") && text.endsWith("'"))
  ) {
    return unquote(text);
  }
  return text;
}

export function parseYaml(source) {
  const text = String(source ?? '').replace(/^\uFEFF/, '').replace(/\r\n?/g, '\n');
  const lines = text.split('\n');
  let index = 0;

  function peek() {
    while (index < lines.length) {
      const line = lines[index];
      if (isBlank(line) || isComment(line)) {
        index += 1;
        continue;
      }
      return { line, indent: indentOf(line), text: line.trim() };
    }
    return null;
  }

  function parseBlockScalar(parentIndent, style) {
    const raw = [];
    while (index < lines.length) {
      const line = lines[index];
      if (isBlank(line)) {
        raw.push('');
        index += 1;
        continue;
      }
      if (indentOf(line) <= parentIndent) break;
      raw.push(line);
      index += 1;
    }
    while (raw.length > 0 && raw[raw.length - 1] === '') raw.pop();
    const nonBlank = raw.filter((l) => l !== '');
    const minIndent = nonBlank.length
      ? Math.min(...nonBlank.map((l) => indentOf(l)))
      : 0;
    const dedented = raw.map((l) => (l === '' ? '' : l.slice(minIndent)));
    let value;
    if (style.startsWith('>')) {
      value = dedented.reduce((acc, line) => {
        if (line === '') return `${acc}\n`;
        return acc === '' ? line : `${acc} ${line}`;
      }, '');
    } else {
      value = dedented.join('\n');
    }
    if (!style.endsWith('-')) value += '\n';
    return value;
  }

  function parseNode(indent) {
    const token = peek();
    if (!token || token.indent < indent) return null;
    if (token.text.startsWith('- ') || token.text === '-') return parseSequence(token.indent);
    return parseMapping(token.indent);
  }

  function parseMapping(indent, initial = null) {
    const obj = {};
    let first = initial;
    for (;;) {
      let text;
      if (first) {
        text = first;
        first = null;
      } else {
        const token = peek();
        if (!token || token.indent < indent) break;
        if (token.indent > indent) {
          throw new YamlError(`Unexpected indentation: "${token.text}"`, index + 1);
        }
        if (token.text.startsWith('- ') || token.text === '-') break;
        text = token.text;
        index += 1;
      }
      const kv = splitKey(text);
      if (!kv) {
        throw new YamlError(`Expected "key: value", got "${text}"`, index);
      }
      if (kv.rest === '') {
        const next = peek();
        if (next && next.indent > indent) {
          obj[kv.key] = parseNode(next.indent);
        } else {
          obj[kv.key] = null;
        }
      } else if (/^[|>][-+]?$/.test(kv.rest)) {
        obj[kv.key] = parseBlockScalar(indent, kv.rest);
      } else {
        obj[kv.key] = parseScalar(kv.rest);
      }
    }
    return obj;
  }

  function parseSequence(indent) {
    const arr = [];
    for (;;) {
      const token = peek();
      if (!token || token.indent < indent) break;
      if (token.indent > indent) {
        throw new YamlError(`Unexpected indentation: "${token.text}"`, index + 1);
      }
      if (!(token.text.startsWith('- ') || token.text === '-')) break;
      const rest = token.text === '-' ? '' : token.text.slice(2).trim();
      index += 1;
      if (rest === '') {
        const next = peek();
        arr.push(next && next.indent > indent ? parseNode(next.indent) : null);
        continue;
      }
      if (/^[|>][-+]?$/.test(rest)) {
        arr.push(parseBlockScalar(indent, rest));
        continue;
      }
      if (splitKey(rest)) {
        const virtualIndent = indent + 2;
        const item = {};
        // Parse the first entry of the inline map, then continue with siblings.
        const kv = splitKey(rest);
        if (kv.rest === '') {
          const next = peek();
          if (next && next.indent > virtualIndent) {
            item[kv.key] = parseNode(next.indent);
          } else if (next && next.indent === virtualIndent && !next.text.startsWith('-')) {
            item[kv.key] = parseMapping(virtualIndent);
          } else {
            item[kv.key] = null;
          }
        } else if (/^[|>][-+]?$/.test(kv.rest)) {
          item[kv.key] = parseBlockScalar(virtualIndent, kv.rest);
        } else {
          item[kv.key] = parseScalar(kv.rest);
        }
        const next = peek();
        if (next && next.indent === virtualIndent && !next.text.startsWith('-')) {
          Object.assign(item, parseMapping(virtualIndent));
        } else if (next && next.indent > virtualIndent) {
          throw new YamlError(`Unexpected indentation in sequence item: "${next.text}"`, index + 1);
        }
        arr.push(item);
        continue;
      }
      arr.push(parseScalar(rest));
    }
    return arr;
  }

  const token = peek();
  if (!token) return null;
  const result = parseNode(token.indent);
  const trailing = peek();
  if (trailing) {
    throw new YamlError(`Unexpected content: "${trailing.text}"`, index + 1);
  }
  return result;
}

function needsQuotes(value) {
  if (value === '') return true;
  if (/^[\s#\-?:,[\]{}&*!|>'"%@`]/.test(value)) return true;
  if (/\s#|:\s|:$|\n/.test(value)) return true;
  if (/^(true|false|null|~|-?\d+(\.\d+)?)$/i.test(value)) return true;
  return false;
}

function quote(value) {
  return `"${String(value).replace(/\\/g, '\\\\').replace(/"/g, '\\"')}"`;
}

function dumpScalar(value) {
  if (value === null || value === undefined) return 'null';
  if (typeof value === 'boolean' || typeof value === 'number') return String(value);
  const text = String(value);
  if (text.includes('\n')) return null;
  return needsQuotes(text) ? quote(text) : text;
}

function dumpValue(value, indent) {
  const pad = ' '.repeat(indent);
  if (Array.isArray(value)) {
    if (value.length === 0) return '[]';
    return value
      .map((item) => {
        if (item !== null && typeof item === 'object' && !Array.isArray(item)) {
          const entries = Object.entries(item);
          if (entries.length === 0) return `${pad}- {}`;
          const [firstKey, firstValue] = entries[0];
          const firstRendered = dumpValue(firstValue, indent + 2);
          const head = firstRendered.startsWith('\n')
            ? `${pad}- ${firstKey}:${firstRendered}`
            : `${pad}- ${firstKey}: ${firstRendered}`;
          const rest = entries.slice(1).map(([k, v]) => dumpEntry(k, v, indent + 2)).join('');
          const rendered = `${head.endsWith('\n') ? head : `${head}\n`}${rest}`;
          return rendered.endsWith('\n') ? rendered.slice(0, -1) : rendered;
        }
        const rendered = dumpValue(item, indent + 2);
        if (Array.isArray(item)) return `${pad}-\n${rendered}`;
        if (rendered.startsWith('\n')) return `${pad}-${rendered}`.replace(/\n$/, '');
        return `${pad}- ${rendered}`;
      })
      .join('\n');
  }
  if (value !== null && typeof value === 'object') {
    const entries = Object.entries(value);
    if (entries.length === 0) return '{}';
    return entries.map(([k, v]) => dumpEntry(k, v, indent)).join('');
  }
  return dumpScalar(value);
}

function dumpEntry(key, value, indent) {
  const pad = ' '.repeat(indent);
  const safeKey = /^[A-Za-z0-9_.-]+$/.test(key) ? key : quote(key);
  if (Array.isArray(value)) {
    if (value.length === 0) return `${pad}${safeKey}: []\n`;
    const rendered = dumpValue(value, indent + 2);
    return `${pad}${safeKey}:\n${rendered.endsWith('\n') ? rendered : `${rendered}\n`}`;
  }
  if (value !== null && typeof value === 'object') {
    if (Object.keys(value).length === 0) return `${pad}${safeKey}: {}\n`;
    const rendered = dumpValue(value, indent + 2);
    return `${pad}${safeKey}:\n${rendered.endsWith('\n') ? rendered : `${rendered}\n`}`;
  }
  if (typeof value === 'string' && value.includes('\n')) {
    const keepTrailingNewline = value.endsWith('\n');
    const lines = value.replace(/\n$/, '').split('\n');
    const body = lines.map((l) => `${pad}  ${l}`).join('\n');
    return `${pad}${safeKey}: ${keepTrailingNewline ? '|' : '|-'}\n${body}\n`;
  }
  return `${pad}${safeKey}: ${dumpScalar(value)}\n`;
}

export function dumpYaml(value) {
  const rendered = dumpValue(value, 0);
  return rendered.endsWith('\n') ? rendered : `${rendered}\n`;
}

export { YamlError };
