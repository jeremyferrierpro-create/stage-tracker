import { validateHardConstraints, calculateSoftConstraints, detectIncompatibilities, rankOptions } from './analyzer-deterministic.mjs';
import { generateDecisionDocument, exportToJSON, exportToMarkdown } from './generator.mjs';

/**
 * Evaluate technology decision based on project context.
 * Returns a complete decision document.
 */
export async function evaluateTechnologyDecision(projectContext, options = {}) {
  const context = {
    projectType: projectContext.projectType,
    businessGoals: projectContext.businessGoals || [],
    functionalConstraints: projectContext.functionalConstraints || [],
    nonFunctionalConstraints: projectContext.nonFunctionalConstraints || [],
    skills: projectContext.skills || [],
    budget: projectContext.budget,
    timeline: projectContext.timeline,
    platform: projectContext.platform || [],
    license: projectContext.license || [],
    features: projectContext.features || [],
    compliance: projectContext.compliance || [],
    scalability: projectContext.scalability
  };
  
  // Step 1: Validate hard constraints
  const { validOptions, eliminated } = await validateHardConstraints(
    options.technologyOptions || [],
    context
  );
  
  // Step 2: Calculate soft constraints
  const scores = calculateSoftConstraints(validOptions, context);
  
  // Step 3: Detect incompatibilities
  const incompatibilities = detectIncompatibilities(validOptions);
  
  // Step 4: Rank options
  const ranked = rankOptions(validOptions, scores, incompatibilities);
  
  // Step 5: AIDF provides deterministic facts only. Interpretation of
  // trade-offs, risks and uncertainties is delegated to the AI agent that
  // invokes the command; no AIDF-owned model exists or is required.
  const agentAnalysis = buildAgentDelegation(ranked);

  // Step 6: Generate recommendation
  const recommendation = generateRecommendation(ranked, agentAnalysis);
  
  // Step 7: Generate document
  const document = generateDecisionDocument(context, {
    deterministic: {
      summary: {
        totalOptions: options.technologyOptions?.length || 0,
        validOptions: validOptions.length,
        shortlistedOptions: ranked.shortlisted?.length || 0,
        eliminatedOptions: eliminated.length
      },
      eliminated,
      scores,
      incompatibilities
    },
    llm: null,
    delegation: agentAnalysis.delegation
  }, recommendation);
  
  return document;
}

/**
 * Build the delegation record for interpretive analysis.
 * AIDF is a governance framework, not an application: it owns no model.
 * The invoking AI agent performs trade-off, risk and architecture
 * interpretation on top of the deterministic facts emitted here.
 */
function buildAgentDelegation(ranked) {
  const shortlisted = ranked.shortlisted ?? [];
  const uncertainties = shortlisted.slice(0, 3).map((s) => ({
    component: s.option,
    unknowns: [
      'trade-off interpretation',
      'risk assessment',
      'architecture fit',
      'option data completeness',
    ],
  }));
  return {
    tradeoffs: [],
    risks: [],
    uncertainties,
    architecture: [],
    delegation: {
      delegatedTo: 'invoking-agent',
      note: 'AIDF provides deterministic facts only (constraints, scores, eliminations). Trade-off, risk and uncertainty interpretation is performed by the AI agent invoking the command; AIDF configures no model and requires no credentials.',
    },
  };
}

/**
 * Generate recommendation from analysis results.
 */
function generateRecommendation(ranked, llmAnalysis) {
  if (!ranked.shortlisted || ranked.shortlisted.length === 0) {
    return {
      stack: [],
      confidence: 'low',
      risks: ['No viable options found'],
      uncertainties: [],
      suggestedTests: []
    };
  }
  
  // Get top option as recommendation
  const topOption = ranked.shortlisted[0];
  
  // Build stack
  const stack = [
    {
      component: 'primary',
      option: topOption.option,
      version: '1.0.0', // Would be filled from option definition
      reason: `Highest score: ${topOption.totalScore.toFixed(2)}`
    }
  ];
  
  // Add risks from LLM analysis
  const risks = llmAnalysis.risks || [];
  
  // Add uncertainties from LLM analysis
  const uncertainties = llmAnalysis.uncertainties || [];
  
  // Determine confidence based on scores and uncertainties
  let confidence = 'medium';
  if (topOption.totalScore >= 0.8 && uncertainties.length === 0) {
    confidence = 'high';
  } else if (topOption.totalScore < 0.6) {
    confidence = 'low';
  }
  
  return {
    stack,
    confidence,
    risks: risks.map(r => ({
      risk: r.vulnerabilities?.join(', ') || r.description || 'Unknown risk',
      impact: 'medium',
      mitigation: r.mitigation?.join(', ')
    })),
    uncertainties: uncertainties.map(u => ({
      component: u.component,
      uncertainty: u.unknowns?.join(', ') || 'Unknown'
    })),
    suggestedTests: [
      `Performance benchmark for ${topOption.option}`,
      `Security audit for ${topOption.option}`,
      `Team skill assessment for ${topOption.option}`
    ]
  };
}


