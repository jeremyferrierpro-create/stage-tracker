#!/usr/bin/env node
import { main } from '../framework/src/cli.mjs';

main(process.argv.slice(2)).then((code) => {
  process.exitCode = code;
}).catch((error) => {
  process.stderr.write(`aidf: fatal: ${error && error.message ? error.message : String(error)}\n`);
  process.exitCode = 70;
});
