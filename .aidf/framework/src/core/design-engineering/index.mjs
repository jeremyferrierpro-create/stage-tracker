// L3: Design Engineering Engine
// Main entry point for the design engineering engine

export { classifyComplexity } from './complexity-classifier.mjs';
export { runDesignWorkflow } from './engine.mjs';
export { validateFigma, testConnection, verifyIdentity, checkPermissions, checkFile } from './figma/validators.mjs';
export { generateSVGWireframe, generateHTMLWireframe } from './fallback/wireframes.mjs';
export { generateCSSMockup } from './fallback/mockups.mjs';
export { captureReference, captureImplementation, generateDiff, createReviewRequest, processReview } from './visual-parity/capture.mjs';
