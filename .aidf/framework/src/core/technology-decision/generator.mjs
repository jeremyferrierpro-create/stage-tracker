/**
 * Decision document generator for technology decisions.
 */

import { generateRequirementId } from '../requirements/registry.mjs';

/**
 * Generate a complete decision document.
 */
export function generateDecisionDocument(context, analysis, recommendation) {
  const document = {
    meta: {
      version: '1.0.0',
      id: `TECH-DECISION-${new Date().getFullYear()}-${String(Math.floor(Math.random() * 1000)).padStart(3, '0')}`,
      timestamp: new Date().toISOString(),
      author: 'Kiro',
      status: 'draft'
    },
    context,
    analysis: {
      deterministic: analysis.deterministic,
      llm: analysis.llm ?? null,
      delegation: analysis.delegation ?? null
    },
    options: {
      shortlisted: [],
      eliminated: analysis.deterministic?.eliminated || [],
      incompatible: analysis.deterministic?.incompatible || []
    },
    comparison: {
      matrix: generateComparisonMatrix(analysis),
      tradeoffs: generateTradeoffs(analysis)
    },
    recommendation: {
      stack: recommendation.stack || [],
      confidence: recommendation.confidence || 'medium',
      risks: recommendation.risks || [],
      uncertainties: recommendation.uncertainties || [],
      suggestedTests: recommendation.suggestedTests || []
    },
    humanApproval: {
      required: true,
      approved: false,
      approver: null,
      comments: null,
      timestamp: null
    }
  };
  
  // Add shortlisted options from analysis
  if (analysis.deterministic?.scores) {
    document.options.shortlisted = analysis.deterministic.scores.map(s => ({
      id: s.option,
      reason: `Score: ${s.totalScore.toFixed(2)}`
    }));
  }
  
  return document;
}

/**
 * Generate comparison matrix.
 */
function generateComparisonMatrix(analysis) {
  const matrix = [];
  
  if (!analysis.deterministic?.scores) return matrix;
  
  const scores = analysis.deterministic.scores;
  if (scores.length < 2) return matrix;
  
  const criteria = ['cost', 'performance', 'ecosystem', 'maintenance', 'scalability'];
  
  for (const criterion of criteria) {
    const option1 = scores[0];
    const option2 = scores[1];
    
    let value1 = '-', value2 = '-';
    let winner = '-';
    
    // Simple comparison logic
    if (criterion === 'cost' && option1.scores?.totalScore && option2.scores?.totalScore) {
      value1 = option1.totalScore.toFixed(2);
      value2 = option2.totalScore.toFixed(2);
      winner = option1.totalScore > option2.totalScore ? option1.option : option2.option;
    }
    
    matrix.push({
      criteria: criterion,
      [option1.option]: value1,
      [option2.option]: value2,
      winner
    });
  }
  
  return matrix;
}

/**
 * Generate trade-offs summary.
 */
function generateTradeoffs(analysis) {
  const tradeoffs = [];
  
  if (analysis.llm?.tradeoffs) {
    for (const tradeoff of analysis.llm.tradeoffs) {
      tradeoffs.push(tradeoff.tradeOff);
    }
  }
  
  return tradeoffs;
}

/**
 * Export document as JSON string.
 */
export function exportToJSON(document) {
  return JSON.stringify(document, null, 2);
}

/**
 * Export document as Markdown (ADR format).
 */
export function exportToMarkdown(document) {
  let md = `# ${document.meta.id}: ${document.context.projectType || 'Technology Decision'}\n\n`;
  md += `**Date**: ${new Date().toISOString().split('T')[0]}\n`;
  md += `**Status**: ${document.meta.status}\n\n`;
  
  md += '## Context\n\n';
  for (const [key, value] of Object.entries(document.context)) {
    if (typeof value === 'object' && value !== null) {
      md += `**${key}**: ${JSON.stringify(value)}\n\n`;
    } else {
      md += `**${key}**: ${value}\n\n`;
    }
  }
  
  md += '## Analysis\n\n';
  md += '### Deterministic Analysis\n\n';
  md += `- Total options: ${document.analysis.deterministic?.summary?.totalOptions || 0}\n`;
  md += `- Valid options: ${document.analysis.deterministic?.summary?.validOptions || 0}\n`;
  md += `- Eliminated: ${document.options.eliminated?.length || 0}\n`;
  md += `- Shortlisted: ${document.options.shortlisted?.length || 0}\n`;
  
  if (document.analysis.llm?.tradeoffs) {
    md += '\n### LLM Trade-offs\n\n';
    for (const tradeoff of document.analysis.llm.tradeoffs) {
      md += `- **${tradeoff.option1} vs ${tradeoff.option2}**: ${tradeoff.tradeOff}\n`;
    }
  }
  
  md += '\n## Options\n\n';
  md += '### Shortlisted\n\n';
  for (const option of document.options.shortlisted) {
    md += `- ${option.id}: ${option.reason}\n`;
  }
  
  if (document.options.eliminated.length > 0) {
    md += '\n### Eliminated\n\n';
    for (const option of document.options.eliminated) {
      md += `- **${option.option}**:\n`;
      for (const violation of option.violations) {
        md += `  - ${violation.reason}\n`;
      }
    }
  }
  
  md += '\n## Recommendation\n\n';
  md += '### Proposed Stack\n\n';
  for (const component of document.recommendation.stack) {
    md += `- **${component.component}**: ${component.option} v${component.version}\n`;
    if (component.reason) {
      md += `  - ${component.reason}\n`;
    }
  }
  
  md += `\n**Confidence**: ${document.recommendation.confidence}\n\n`;
  
  if (document.recommendation.risks.length > 0) {
    md += '### Risks\n\n';
    for (const risk of document.recommendation.risks) {
      md += `- **${risk.risk || risk.component}**: ${risk.impact || 'unknown'} impact\n`;
      if (risk.mitigation) {
        md += `  - Mitigation: ${risk.mitigation}\n`;
      }
    }
  }
  
  if (document.recommendation.uncertainties.length > 0) {
    md += '\n### Uncertainties\n\n';
    for (const uncertainty of document.recommendation.uncertainties) {
      md += `- **${uncertainty.component}**: ${uncertainty.uncertainty}\n`;
    }
  }
  
  md += '\n## Human Approval\n\n';
  md += `- Required: ${document.humanApproval.required}\n`;
  md += `- Approved: ${document.humanApproval.approved}\n`;
  if (document.humanApproval.comments) {
    md += `- Comments: ${document.humanApproval.comments}\n`;
  }
  
  return md;
}
