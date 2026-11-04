import { parseArgs } from '../util/args.mjs';
import { resolveProjectRoot, isInitialized, DOCUMENTS, DOC_STATUSES } from '../core/paths.mjs';
import { loadConfig, saveConfig, validateConfig } from '../core/config.mjs';
import { ADAPTER_KEYS } from '../core/adapters/index.mjs';
import { attachLogFile } from '../util/logger.mjs';

export function configArgSpec() {
  return {
    booleans: ['json', 'help'],
    aliases: { h: 'help', t: 'target' },
  };
}

const PROTECTED = new Set(['schemaVersion', 'createdAt', 'updatedAt', 'framework.version']);

function getPath(object, dotted) {
  return dotted.split('.').reduce((acc, key) => (acc == null ? undefined : acc[key]), object);
}

function setPath(object, dotted, value) {
  const parts = dotted.split('.');
  let current = object;
  for (const part of parts.slice(0, -1)) {
    if (current[part] == null || typeof current[part] !== 'object') current[part] = {};
    current = current[part];
  }
  current[parts[parts.length - 1]] = value;
}

function parseValue(raw) {
  if (raw === 'true') return true;
  if (raw === 'false') return false;
  if (raw === 'null') return null;
  if (/^-?\d+$/.test(raw)) return Number.parseInt(raw, 10);
  if (/^-?\d+\.\d+$/.test(raw)) return Number.parseFloat(raw);
  if ((raw.startsWith('[') && raw.endsWith(']')) || (raw.startsWith('{') && raw.endsWith('}'))) {
    try {
      return JSON.parse(raw);
    } catch {
      return raw;
    }
  }
  return raw;
}

export async function configCommand(argv, ctx) {
  const args = parseArgs(argv, configArgSpec());
  const sub = args._[0] ?? 'list';
  if (args.help) {
    ctx.logger.info('Usage: aidf config <list|get|set|validate> [path] [value] [--json]');
    return 0;
  }
  const root = resolveProjectRoot({ target: args.target, cwd: process.cwd() });
  if (!isInitialized(root)) {
    ctx.logger.error(`AIDF is not initialized in ${root}. Run "aidf install" first.`);
    return 1;
  }
  await attachLogFile(ctx.logger, root).catch(() => null);
  const config = await loadConfig(root);

  if (sub === 'list') {
    if (ctx.json) process.stdout.write(`${JSON.stringify(config, null, 2)}\n`);
    else process.stdout.write(`${JSON.stringify(config, null, 2)}\n`);
    return 0;
  }

  if (sub === 'get') {
    const key = args._[1];
    if (!key) {
      ctx.logger.error('Usage: aidf config get <path>');
      return 1;
    }
    const value = getPath(config, key);
    if (value === undefined) {
      ctx.logger.error(`Unknown configuration path: ${key}`);
      return 1;
    }
    process.stdout.write(`${typeof value === 'string' ? value : JSON.stringify(value, null, 2)}\n`);
    return 0;
  }

  if (sub === 'set') {
    const key = args._[1];
    const raw = args._.slice(2).join(' ');
    if (!key || raw === '') {
      ctx.logger.error('Usage: aidf config set <path> <value>');
      return 1;
    }
    if (PROTECTED.has(key)) {
      ctx.logger.error(`Refusing to set protected path "${key}".`);
      return 1;
    }
    if (key === 'adapter' && !ADAPTER_KEYS.includes(raw)) {
      ctx.logger.error(`Invalid adapter "${raw}". Allowed: ${ADAPTER_KEYS.join(', ')}`);
      return 1;
    }
    if (key.startsWith('documents.')) {
      const [, doc, field] = key.split('.');
      if (!DOCUMENTS.includes(doc)) {
        ctx.logger.error(`Unknown document "${doc}".`);
        return 1;
      }
      if (field === 'status' && !DOC_STATUSES.includes(raw)) {
        ctx.logger.error(`Invalid status "${raw}". Allowed: ${DOC_STATUSES.join(', ')}`);
        return 1;
      }
    }
    const value = parseValue(raw);
    setPath(config, key, value);
    const errors = validateConfig(config);
    if (errors.length > 0) {
      ctx.logger.error(`Configuration rejected: ${errors.join('; ')}`);
      return 1;
    }
    await saveConfig(root, config);
    ctx.logger.info(`${key} = ${typeof value === 'string' ? value : JSON.stringify(value)}`);
    return 0;
  }

  if (sub === 'validate') {
    const errors = validateConfig(config);
    if (ctx.json) process.stdout.write(`${JSON.stringify({ valid: errors.length === 0, errors }, null, 2)}\n`);
    else if (errors.length === 0) ctx.logger.info('Configuration: PASS');
    else for (const error of errors) ctx.logger.error(error);
    return errors.length === 0 ? 0 : 1;
  }

  ctx.logger.error(`Unknown config subcommand: ${sub}`);
  return 1;
}
