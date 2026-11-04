import fs from 'node:fs';
import path from 'node:path';
import { ensureDir } from './fsx.mjs';

const LEVELS = { debug: 10, info: 20, warn: 30, error: 40, silent: 100 };

export class Logger {
  constructor(options = {}) {
    this.level = options.level ?? 'info';
    this.json = Boolean(options.json);
    this.logFile = options.logFile ?? null;
    this.records = [];
    this.redact = options.redact ?? [];
  }

  setLogFile(file) {
    this.logFile = file;
  }

  write(level, message, data) {
    const record = {
      ts: new Date().toISOString(),
      level,
      message,
      ...(data ? { data } : {}),
    };
    this.records.push(record);
    if (this.logFile) {
      try {
        fs.mkdirSync(path.dirname(this.logFile), { recursive: true });
        fs.appendFileSync(this.logFile, `${JSON.stringify(record)}\n`, 'utf8');
      } catch {
        // Logging must never break the command.
      }
    }
    if (LEVELS[level] < LEVELS[this.level]) return;
    if (this.json) {
      process.stdout.write(`${JSON.stringify(record)}\n`);
      return;
    }
    const stream = level === 'error' || level === 'warn' ? process.stderr : process.stdout;
    const prefix = level === 'error' ? 'ERROR' : level === 'warn' ? 'WARN' : level === 'debug' ? 'DEBUG' : 'INFO';
    stream.write(`[${prefix}] ${message}${data ? ` ${JSON.stringify(data)}` : ''}\n`);
  }

  debug(message, data) { this.write('debug', message, data); }
  info(message, data) { this.write('info', message, data); }
  warn(message, data) { this.write('warn', message, data); }
  error(message, data) { this.write('error', message, data); }

  table(rows, columns) {
    if (this.json) {
      this.info('table', { rows });
      return;
    }
    const widths = columns.map((c) => Math.max(c.label.length, ...rows.map((r) => String(r[c.key] ?? '').length)));
    const line = (cells) => cells.map((cell, i) => String(cell ?? '').padEnd(widths[i])).join('  ');
    process.stdout.write(`${line(columns.map((c) => c.label))}\n`);
    process.stdout.write(`${line(widths.map((w) => '-'.repeat(w)))}\n`);
    for (const row of rows) process.stdout.write(`${line(columns.map((c) => row[c.key]))}\n`);
  }
}

export function createLogger(options) {
  return new Logger(options);
}

export async function attachLogFile(logger, root) {
  const dir = path.join(root, '.aidf', 'logs');
  await ensureDir(dir);
  const file = path.join(dir, `aidf-${new Date().toISOString().slice(0, 10)}.jsonl`);
  logger.setLogFile(file);
  return file;
}
