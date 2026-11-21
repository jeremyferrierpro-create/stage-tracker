import { FRAMEWORKS, databases, servers, cloud } from '../technology-decision/options/index.mjs';

export const HARD_CONSTRAINTS = {
  BUDGET: 'budget',
  PLATFORM: 'platform',
  LICENSE: 'license',
  FEATURES: 'features',
  COMPLIANCE: 'compliance'
};

export const SOFT_CONSTRAINTS = {
  SKILL_MATCH: 'skillMatch',
  ECOSYSTEM: 'ecosystem',
  COMMUNITY: 'community',
  SCALABILITY: 'scalability',
  MAINTENANCE: 'maintenance'
};

/**
 * Validate hard constraints against technology options.
 * Eliminates options that don't meet hard requirements.
 */
export async function validateHardConstraints(options, projectContext) {
  const validOptions = [];
  const eliminated = [];
  
  for (const option of options) {
    const violations = [];
    
    // Budget check
    if (projectContext.budget && option.costs) {
      if (option.costs.estimatedCost > projectContext.budget.max) {
        violations.push({
          constraint: HARD_CONSTRAINTS.BUDGET,
          optionValue: option.costs.estimatedCost,
          required: projectContext.budget.max,
          reason: `Option cost ${option.costs.estimatedCost} exceeds budget ${projectContext.budget.max} ${projectContext.budget.currency || 'USD'}`
        });
      }
    }
    
    // Platform check
    if (projectContext.platform && option.platform) {
      const missing = projectContext.platform.filter(
        p => !option.platform.includes(p)
      );
      if (missing.length > 0) {
        violations.push({
          constraint: HARD_CONSTRAINTS.PLATFORM,
          optionValue: option.platform,
          required: projectContext.platform,
          reason: `Option platform ${option.platform} missing required: ${missing.join(', ')}`
        });
      }
    }
    
    // License check
    if (projectContext.license && option.license) {
      if (!projectContext.license.includes(option.license)) {
        violations.push({
          constraint: HARD_CONSTRAINTS.LICENSE,
          optionValue: option.license,
          required: projectContext.license,
          reason: `Option license ${option.license} not in acceptable list`
        });
      }
    }
    
    // Feature check
    if (projectContext.features && option.features?.required) {
      const missing = option.features.required.filter(
        f => !projectContext.features.includes(f)
      );
      if (missing.length > 0) {
        violations.push({
          constraint: HARD_CONSTRAINTS.FEATURES,
          optionValue: option.features.required,
          required: projectContext.features,
          reason: `Option missing required features: ${missing.join(', ')}`
        });
      }
    }
    
    // Compliance check
    if (projectContext.compliance && option.compliance) {
      const missingCompliance = projectContext.compliance.filter(
        c => !option.compliance.includes(c)
      );
      if (missingCompliance.length > 0) {
        violations.push({
          constraint: HARD_CONSTRAINTS.COMPLIANCE,
          optionValue: option.compliance,
          required: projectContext.compliance,
          reason: `Option missing compliance: ${missingCompliance.join(', ')}`
        });
      }
    }
    
    if (violations.length === 0) {
      validOptions.push(option);
    } else {
      eliminated.push({ option: option.id, violations });
    }
  }
  
  return { validOptions, eliminated };
}

/**
 * Calculate soft constraint satisfaction scores.
 */
export function calculateSoftConstraints(options, projectContext) {
  return options.map(option => {
    const scores = {};
    
    // Skill match
    if (projectContext.skills && option.maintenance?.skillRequirements) {
      const skillCount = option.maintenance.skillRequirements.length;
      if (skillCount === 0) {
        scores.skillMatch = 1.0;
      } else {
        const matchingSkills = projectContext.skills.filter(s => 
          option.maintenance.skillRequirements.includes(s)
        );
        scores.skillMatch = matchingSkills.length / skillCount;
      }
    }
    
    // Ecosystem quality
    if (option.ecosystem?.documentationQuality) {
      const qualityMap = {
        'poor': 0.2,
        'fair': 0.4,
        'good': 0.7,
        'excellent': 1.0
      };
      scores.ecosystem = qualityMap[option.ecosystem.documentationQuality] || 0.5;
    }
    
    // Community size
    if (option.ecosystem?.communitySize) {
      const sizeMap = {
        'small': 0.3,
        'medium': 0.5,
        'large': 0.7,
        'huge': 1.0
      };
      scores.community = sizeMap[option.ecosystem.communitySize] || 0.5;
    }
    
    // Scalability match
    if (projectContext.scalability) {
      if (projectContext.scalability.horizontal && option.scalability?.horizontal) {
        scores.scalability = 1.0;
      } else if (projectContext.scalability.horizontal && !option.scalability?.horizontal) {
        scores.scalability = 0.2;
      } else if (projectContext.scalability.vertical && option.scalability?.vertical) {
        scores.scalability = 1.0;
      } else {
        scores.scalability = 0.7;
      }
    }
    
    // Maintenance complexity
    if (option.maintenance?.complexity) {
      const complexityMap = {
        'simple': 1.0,
        'moderate': 0.7,
        'complex': 0.4
      };
      scores.maintenance = complexityMap[option.maintenance.complexity] || 0.7;
    }
    
    // Calculate total score (average of all scores)
    const scoreValues = Object.values(scores);
    scores.totalScore = scoreValues.length > 0 
      ? scoreValues.reduce((a, b) => a + b, 0) / scoreValues.length 
      : 0.5; // Default if no scores
    
    return {
      option: option.id,
      scores,
      totalScore: scores.totalScore
    };
  });
}

/**
 * Detect known incompatibilities between options.
 */
export function detectIncompatibilities(options) {
  const incompatibilities = [];
  
  for (let i = 0; i < options.length; i++) {
    for (let j = i + 1; j < options.length; j++) {
      const opt1 = options[i];
      const opt2 = options[j];
      
      const incompatible1 = opt1.compatibility?.incompatibleWith?.includes(opt2.id);
      const incompatible2 = opt2.compatibility?.incompatibleWith?.includes(opt1.id);
      
      if (incompatible1 || incompatible2) {
        incompatibilities.push({
          option1: opt1.id,
          option2: opt2.id,
          reason: incompatible1 
            ? opt1.compatibility.incompatibleWith.find(r => r.includes(opt2.id)) || 'Incompatible versions'
            : opt2.compatibility.incompatibleWith.find(r => r.includes(opt1.id)) || 'Incompatible versions'
        });
      }
    }
  }
  
  return incompatibilities;
}

/**
 * Rank options based on scores and incompatibilities.
 */
export function rankOptions(validOptions, scores, incompatibilities) {
  // Sort by total score (descending)
  const sorted = [...scores].sort((a, b) => b.totalScore - a.totalScore);
  
  // Filter out options that are incompatible with top choices
  const incompatibleOptionIds = new Set();
  for (const inc of incompatibilities) {
    incompatibleOptionIds.add(inc.option1);
    incompatibleOptionIds.add(inc.option2);
  }
  
  const shortlisted = sorted.filter(s => s.totalScore >= 0.6 && !incompatibleOptionIds.has(s.option));
  
  // Include all incompatible options for tracking
  const incompatible = incompatibilities;
  
  return {
    shortlisted,
    incompatible
  };
}
