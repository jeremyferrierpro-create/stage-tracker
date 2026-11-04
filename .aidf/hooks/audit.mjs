#!/usr/bin/env node
/**
 * Qoder PostToolUse hook: append a redacted audit record for file edits.
 * Only metadata is stored: event, tool, file path relative to cwd, timestamp.
 * Tool input and file content are never logged (they may contain secrets).
 * Never blocks. Fails open.
 */
import fs from 'node:fs';
import path from 'node:path';

function readStdin() {
  try {
    return fs.readFileSync(0, 'utf8');
  } catch {
    return '';
  }
}

try {
  const raw = readStdin();
  const input = JSON.parse(raw || '{}');
  const cwd = input.cwd || process.env.QODER_CWD || process.cwd();
  const logDir = path.join(cwd, '.aidf', 'logs');
  fs.mkdirSync(logDir, { recursive: true });
  const date = new Date().toISOString().slice(0, 10);
  const file = path.join(logDir, `audit-${date}.jsonl`);
  const rawPath = input.tool_input?.file_path || input.tool_input?.path || process.env.QODER_TOOL_INPUT_FILE_PATH || '';
  let relative = '';
  try {
    relative = rawPath ? path.relative(cwd, path.resolve(cwd, rawPath)).replace(/\\/g, '/') : '';
  } catch {
    relative = '';
  }
  const record = {
    ts: new Date().toISOString(),
    event: input.hook_event_name || 'PostToolUse',
    tool: input.tool_name || process.env.QODER_TOOL_NAME || 'unknown',
    file: relative.slice(0, 300),
    session: String(input.session_id || '').slice(0, 64),
  };
  fs.appendFileSync(file, `${JSON.stringify(record)}\n`, 'utf8');
  process.exit(0);
} catch {
  process.exit(0);
}
