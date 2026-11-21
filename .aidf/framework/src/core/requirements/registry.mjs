import { aidfPaths, AIDF_VERSION } from '../paths.mjs';
import { readJson, writeJson, exists, sha256Text } from '../../util/fsx.mjs';

export const REQUIREMENT_STATUSES = ['DRAFT', 'VALIDATED', 'BLOCKED', 'OUTDATED'];
export const REQUIREMENT_TYPES = ['FUNCTIONAL', 'NON_FUNCTIONAL'];
export const REQUIREMENT_PRIORITIES = ['must', 'should', 'could'];
export const REQUIREMENT_PATTERN = /^REQ-(F|NF)-\d{3}$/;

/**
 * Generate a stable requirement ID.
 */
export function generateRequirementId(type, index) {
  if (!REQUIREMENT_TYPES.includes(type)) {
    throw new Error(`Invalid requirement type: ${type}`);
  }
  return `REQ-${type === 'FUNCTIONAL' ? 'F' : 'NF'}-${String(index).padStart(3, '0')}`;
}

/**
 * Load the requirements registry.
 */
export async function loadRequirementsRegistry(root) {
  const { readJson } = await import('../../util/fsx.mjs');
  const file = aidfPaths(root).artefacts.requirements;
  
  const data = await readJson(file, null);
  if (!data) {
    return {
      schemaVersion: '1.0.0',
      requirements: [],
      updatedAt: null
    };
  }
  
  return data;
}

/**
 * Save the requirements registry.
 */
export async function saveRequirementsRegistry(root, registry) {
  const { writeJson } = await import('../../util/fsx.mjs');
  const file = aidfPaths(root).artefacts.requirements;
  registry.updatedAt = new Date().toISOString();
  await writeJson(file, registry);
}

/**
 * Add a requirement to the registry.
 */
export async function addRequirement(root, requirement, options = {}) {
  const registry = await loadRequirementsRegistry(root);
  
  // Validate requirement ID
  if (requirement.id && !REQUIREMENT_PATTERN.test(requirement.id)) {
    throw new Error(`Invalid requirement ID format: ${requirement.id}. Must match: REQ-(F|NF)-XXX`);
  }
  
  // Generate ID if not provided
  if (!requirement.id) {
    const nextIndex = registry.requirements.length + 1;
    requirement.id = generateRequirementId(requirement.type, nextIndex);
  }
  
  // Check for duplicates
  if (registry.requirements.some(r => r.id === requirement.id)) {
    throw new Error(`Requirement ID already exists: ${requirement.id}`);
  }
  
  // Set required fields
  const now = new Date().toISOString();
  const artifact = {
    id: requirement.id,
    schemaVersion: '1.0.0',
    createdAt: now,
    updatedAt: now,
    status: requirement.status || 'DRAFT',
    type: requirement.type,
    title: requirement.title,
    description: requirement.description,
    priority: requirement.priority || 'should',
    acceptanceCriteria: requirement.acceptanceCriteria || [],
    source: {
      type: options.human ? 'human' : 'generated',
      author: options.human ? (options.by || 'human') : 'aidf',
      createdAt: now,
      generator: 'requirements-registry@1.0.0'
    },
    links: requirement.links || {},
    validation: {
      schemaVersion: '1.0.0',
      timestamp: now,
      valid: true,
      errors: [],
      warnings: []
    }
  };
  
  registry.requirements.push(artifact);
  await saveRequirementsRegistry(root, registry);
  
  return artifact;
}

/**
 * Update a requirement in the registry.
 */
export async function updateRequirement(root, id, updates, options = {}) {
  const registry = await loadRequirementsRegistry(root);
  const index = registry.requirements.findIndex(r => r.id === id);
  
  if (index === -1) {
    throw new Error(`Requirement not found: ${id}`);
  }
  
  // Update fields
  const existing = registry.requirements[index];
  const updated = {
    ...existing,
    ...updates,
    updatedAt: new Date().toISOString()
  };
  
  registry.requirements[index] = updated;
  await saveRequirementsRegistry(root, registry);
  
  return updated;
}

/**
 * Mark a requirement as BLOCKED (soft delete).
 */
export async function deleteRequirement(root, id, options = {}) {
  const registry = await loadRequirementsRegistry(root);
  const index = registry.requirements.findIndex(r => r.id === id);
  
  if (index === -1) {
    throw new Error(`Requirement not found: ${id}`);
  }
  
  // Mark as BLOCKED instead of removing
  registry.requirements[index].status = 'BLOCKED';
  registry.requirements[index].updatedAt = new Date().toISOString();
  
  await saveRequirementsRegistry(root, registry);
  return registry.requirements[index];
}

/**
 * Calculate coverage for requirements.
 * Coverage is computed, never declared.
 * Returns { coverage, details }
 */
export async function calculateCoverage(root, options = {}) {
  const registry = await loadRequirementsRegistry(root);
  const { loadEvidenceIndex } = await import('../evidence.mjs');
  
  const evidenceIndex = await loadEvidenceIndex(root);
  const details = [];
  
  for (const req of registry.requirements) {
    const covered = checkRequirementCoverage(req, evidenceIndex);
    details.push({
      id: req.id,
      status: req.status,
      type: req.type,
      covered,
      tests: req.acceptanceCriteria.length,
      passed: covered ? req.acceptanceCriteria.length : 0
    });
  }
  
  const total = details.length;
  const covered = details.filter(d => d.covered).length;
  const untested = details.filter(d => d.tests === 0).length;
  const testedButNotPassing = details.filter(d => d.tests > 0 && !d.covered).length;
  
  return {
    coverage: {
      total,
      covered,
      untested,
      testedButNotPassing
    },
    details
  };
}

/**
 * Check if a single requirement is covered.
 * A requirement is covered when ALL acceptance criteria have passing evidence.
 */
function checkRequirementCoverage(req, evidenceIndex) {
  if (!req.acceptanceCriteria || req.acceptanceCriteria.length === 0) {
    return false;
  }
  
  // Check each acceptance criterion
  for (const criterion of req.acceptanceCriteria) {
    const hasPassingEvidence = evidenceIndex.entries.some(entry => {
      // Evidence must reference this requirement or its tests
      const referencesReq = entry.links?.requirement === req.id;
      const referencesTest = entry.links?.test === criterion.id;
      return entry.status === 'PASS' && (referencesReq || referencesTest);
    });
    
    if (!hasPassingEvidence) {
      return false;
    }
  }
  
  return true;
}

/**
 * Find requirements by type, status, or priority.
 */
export function filterRequirements(registry, filters = {}) {
  let result = registry.requirements;
  
  if (filters.type) {
    result = result.filter(r => r.type === filters.type);
  }
  
  if (filters.status) {
    result = result.filter(r => r.status === filters.status);
  }
  
  if (filters.priority) {
    result = result.filter(r => r.priority === filters.priority);
  }
  
  return result;
}