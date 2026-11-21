/**
 * L3: Complexity Classification
 * Determines design workflow based on project characteristics.
 */

export function classifyComplexity(context) {
  const factors = {
    projectSize: classifyByFeatureCount(context.featureCount || 0),
    teamSize: classifyByTeamSize(context.teamSize || 1),
    timeline: classifyByTimeline(context.timelineWeeks || 2),
    budget: classifyByBudget(context.budget || 0)
  };
  
  // Determine overall complexity
  const scores = Object.values(factors).map(f => f.score);
  const avgScore = scores.reduce((a, b) => a + b, 0) / scores.length;
  
  let complexity;
  if (avgScore < 1.5) complexity = 'small';
  else if (avgScore < 2.5) complexity = 'medium';
  else if (avgScore < 3.5) complexity = 'large';
  else complexity = 'enterprise';
  
  // Check for emergency overrides
  if (context.emergency || context.mvpMode || context.maintenance) {
    complexity = 'small';
  }
  
  return {
    complexity,
    factors,
    score: Math.round(avgScore * 100) / 100
  };
}

function classifyByFeatureCount(count) {
  if (count < 5) return { level: 'small', score: 1 };
  if (count < 15) return { level: 'medium', score: 2 };
  if (count < 50) return { level: 'large', score: 3 };
  return { level: 'enterprise', score: 4 };
}

function classifyByTeamSize(size) {
  if (size <= 2) return { level: 'small', score: 1 };
  if (size <= 5) return { level: 'medium', score: 2 };
  if (size <= 15) return { level: 'large', score: 3 };
  return { level: 'enterprise', score: 4 };
}

function classifyByTimeline(weeks) {
  if (weeks < 2) return { level: 'quick', score: 1 };
  if (weeks < 8) return { level: 'standard', score: 2 };
  if (weeks < 16) return { level: 'extended', score: 3 };
  return { level: 'enterprise', score: 4 };
}

function classifyByBudget(budget) {
  if (budget < 10000) return { level: 'small', score: 1 };
  if (budget < 50000) return { level: 'medium', score: 2 };
  if (budget < 200000) return { level: 'large', score: 3 };
  return { level: 'enterprise', score: 4 };
}

/**
 * Select appropriate workflow based on complexity.
 */
export function getWorkflow(complexity) {
  if (complexity === 'small' || complexity === 'quick' || complexity === 'mvp') {
    return 'quick';
  }
  if (complexity === 'medium') {
    return 'standard';
  }
  if (complexity === 'large' || complexity === 'enterprise') {
    return 'extended';
  }
  return 'standard';
}
