// L2: Technology Decision Engine
// Main entry point for the technology decision engine

export { evaluateTechnologyDecision } from './engine.mjs';
export {
  validateHardConstraints,
  calculateSoftConstraints,
  detectIncompatibilities,
  rankOptions
} from './analyzer-deterministic.mjs';
// Interpretation of trade-offs, risks and uncertainties is delegated to the
// invoking AI agent by design; AIDF owns no model (see engine delegation).
export {
  generateDecisionDocument,
  exportToJSON,
  exportToMarkdown
} from './generator.mjs';
