import { classifyComplexity, getWorkflow } from './complexity-classifier.mjs';
import { validateFigma, testConnection, verifyIdentity, checkPermissions, checkFile } from './figma/validators.mjs';
import { generateSVGWireframe, generateHTMLWireframe } from './fallback/wireframes.mjs';
import { generateCSSMockup } from './fallback/mockups.mjs';

/**
 * Main orchestrator for design engineering workflow.
 */
export async function runDesignWorkflow(projectContext) {
  // Step 1: Classify complexity
  const complexity = classifyComplexity(projectContext);
  
  // Step 2: Check Figma availability
  const figmaStatus = await validateFigma(projectContext.figma);
  
  // Step 3: Select workflow
  const workflow = getWorkflow(complexity.complexity);
  
  // Step 4: Execute workflow
  let result;
  if (figmaStatus.status === 'fully_verified') {
    result = await runFigmaWorkflow(projectContext, workflow, figmaStatus);
  } else {
    result = await runFallbackWorkflow(projectContext, workflow, figmaStatus);
  }
  
  // Step 5: Run visual parity check if enabled
  if (projectContext.visualParity?.enabled) {
    const parityResult = await runVisualParityCheck(projectContext);
    result.visualParity = parityResult;
  }
  
  // Step 6: Document everything
  result.complexity = complexity;
  result.workflow = workflow;
  result.figmaStatus = figmaStatus;
  
  return result;
}

/**
 * Run Figma-enabled workflow.
 */
async function runFigmaWorkflow(projectContext, workflow, figmaStatus) {
  return {
    status: 'figma_enabled',
    figmaStatus,
    workflow,
    steps: getFigmaWorkflowSteps(workflow),
    outputs: {
      designFile: projectContext.figma.fileId
    }
  };
}

/**
 * Run fallback workflow when Figma unavailable.
 */
async function runFallbackWorkflow(projectContext, workflow, figmaStatus) {
  const fallbackResult = {
    status: 'fallback',
    figmaStatus,
    workflow,
    reasons: getFallbackReasons(figmaStatus),
    outputs: {}
  };
  
  // Generate wireframes
  if (projectContext.screens) {
    fallbackResult.outputs.wireframes = projectContext.screens.map(screen => 
      generateSVGWireframe(screen)
    );
  }
  
  // Generate mockups
  if (projectContext.theme) {
    fallbackResult.outputs.mockups = generateCSSMockup(projectContext.theme);
  }
  
  // Generate documentation
  fallbackResult.outputs.documentation = generateFallbackDocumentation(figmaStatus);
  
  return fallbackResult;
}

/**
 * Get steps for each workflow type.
 */
function getFigmaWorkflowSteps(workflow) {
  const steps = {
    quick: [
      'User journey map',
      'Basic wireframes',
      'Style guide',
      'Accessibility review'
    ],
    standard: [
      'User personas',
      'User flow diagrams',
      'Wireframes',
      'Interactive prototype',
      'Design system (basic)',
      'Accessibility audit'
    ],
    extended: [
      'UX research',
      'User testing',
      'High-fidelity mockups',
      'Design system (comprehensive)',
      'Accessibility audit',
      'Visual regression suite'
    ]
  };
  
  return steps[workflow] || steps.standard;
}

/**
 * Get reasons for fallback.
 */
function getFallbackReasons(figmaStatus) {
  const reasons = [];
  
  if (figmaStatus.status === 'disabled') {
    reasons.push('Figma integration disabled');
  }
  if (figmaStatus.status === 'configuration_missing') {
    reasons.push('Missing Figma configuration');
  }
  if (figmaStatus.status === 'connection_failed') {
    reasons.push(`Connection failed: ${figmaStatus.error}`);
  }
  if (figmaStatus.status === 'permissions_failed') {
    reasons.push(`Missing permissions: ${figmaStatus.missing?.join(', ')}`);
  }
  if (figmaStatus.status === 'file_failed') {
    reasons.push(`File access failed: ${figmaStatus.error}`);
  }
  
  return reasons;
}

/**
 * Generate fallback documentation.
 */
function generateFallbackDocumentation(figmaStatus) {
  let doc = `# Design Fallback Documentation\n\n`;
  doc += `## Figma Status\n`;
  
  switch (figmaStatus.status) {
    case 'disabled':
      doc += `Figma integration disabled\n`;
      break;
    case 'configuration_missing':
      doc += `Missing Figma configuration\n`;
      break;
    case 'connection_failed':
      doc += `Connection failed: ${figmaStatus.error}\n`;
      break;
    case 'permissions_failed':
      doc += `Missing permissions: ${figmaStatus.missing?.join(', ')}\n`;
      break;
    case 'file_failed':
      doc += `File access failed: ${figmaStatus.error}\n`;
      break;
    default:
      doc += `Unknown issue\n`;
  }
  
  doc += `\n## Generated Outputs\n`;
  doc += `- Wireframes: HTML/SVG format\n`;
  doc += `- Mockups: CSS-based assets\n`;
  doc += `- Screenshots: Implementation captures\n`;
  
  doc += `\n## Limitations\n`;
  doc += `- No Figma collaboration features\n`;
  doc += `- No real-time updates\n`;
  doc += `- Manual version control required\n`;
  
  doc += `\n## Next Steps\n`;
  doc += `- Review locally generated assets\n`;
  doc += `- Make updates to HTML/SVG/CSS files\n`;
  doc += `- Commit to version control\n`;
  
  return doc;
}

/**
 * Run visual parity check.
 */
async function runVisualParityCheck(projectContext) {
  // This would integrate with the visual parity module
  return {
    status: 'awaiting_capture',
    note: 'Visual parity check available when implementation is accessible'
  };
}
