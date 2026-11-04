/**
 * Deterministic argv parser. Supports:
 *   --flag, --key value, --key=value, --no-key, -h
 * Boolean flags are declared so `--target foo` never consumes a flag.
 */

export function parseArgs(argv, spec = {}) {
  const booleans = new Set(spec.booleans ?? []);
  const aliases = spec.aliases ?? {};
  const result = { _: [] };
  for (let i = 0; i < argv.length; i += 1) {
    let token = argv[i];
    if (token === '--') {
      result._.push(...argv.slice(i + 1));
      break;
    }
    if (token.startsWith('-') && token !== '-') {
      let name;
      let inlineValue;
      if (token.startsWith('--')) {
        const body = token.slice(2);
        const eq = body.indexOf('=');
        name = eq >= 0 ? body.slice(0, eq) : body;
        inlineValue = eq >= 0 ? body.slice(eq + 1) : undefined;
      } else {
        name = token.slice(1);
        if (aliases[name]) name = aliases[name];
      }
      if (name.startsWith('no-') && booleans.has(name.slice(3))) {
        result[name.slice(3)] = false;
        continue;
      }
      if (booleans.has(name)) {
        result[name] = inlineValue === undefined ? true : inlineValue !== 'false';
        continue;
      }
      if (inlineValue !== undefined) {
        result[name] = inlineValue;
        continue;
      }
      const next = argv[i + 1];
      if (next !== undefined && (!next.startsWith('-') || next === '-')) {
        result[name] = next;
        i += 1;
      } else {
        result[name] = true;
      }
      continue;
    }
    result._.push(token);
  }
  return result;
}

export function requirePositional(args, index, label) {
  const value = args._[index];
  if (typeof value !== 'string' || value.trim() === '') {
    throw new Error(`Missing ${label}.`);
  }
  return value;
}

export function assertEnum(value, allowed, label) {
  if (value === undefined) return undefined;
  if (!allowed.includes(value)) {
    throw new Error(`Invalid ${label} "${value}". Allowed: ${allowed.join(', ')}`);
  }
  return value;
}
