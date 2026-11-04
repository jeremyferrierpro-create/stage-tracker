#!/usr/bin/env node
/**
 * Qoder SessionStart hook: inject a short AIDF reminder and the current phase.
 * Never blocks. Never prints secrets. Fails open.
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

function safeJson(file, fallback) {
  try {
    return JSON.parse(fs.readFileSync(file, 'utf8'));
  } catch {
    return fallback;
  }
}

try {
  const input = JSON.parse(readStdin() || '{}');
  const cwd = input.cwd || process.cwd();
  const aidf = path.join(cwd, '.aidf');
  const config = safeJson(path.join(aidf, 'config.json'), null);
  const state = safeJson(path.join(aidf, 'state', 'workflow.json'), null);
  if (!config) process.exit(0);
  const phase = state?.currentPhase ?? config.workflow?.currentPhase ?? 'intake';
  const docs = Object.entries(config.documents ?? {})
    .filter(([, v]) => ['BLOCKED', 'OUTDATED'].includes(v?.status))
    .map(([k, v]) => `${k}=${v.status}`);
  const lines = [
    'AIDF is installed in this project.',
    'Read .aidf/AIDF.md and .aidf/docs/constitution.md before acting.',
    `Current phase: ${phase}. Check gates with: node .aidf/bin/aidf.mjs workflow status`,
    'Select context with: node .aidf/bin/aidf.mjs context --task "<request>"',
  ];
  if (docs.length > 0) lines.push(`Contradiction-sensitive documents not clean: ${docs.join(', ')}`);
  lines.push('No PASS without evidence. Never read or print secrets.');
  process.stdout.write(JSON.stringify({
    hookSpecificOutput: {
      hookEventName: 'SessionStart',
      additionalContext: lines.join('\n'),
    },
  }));
  process.exit(0);
} catch {
  process.exit(0);
}
