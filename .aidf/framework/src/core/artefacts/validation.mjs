import { readText, readJson, exists, sha256Text } from '../../util/fsx.mjs';
import { FRAMEWORK_ROOT } from '../paths.mjs';
import { parseYaml } from '../../util/yaml.mjs';

export const ARTIFACT_STATUSES = ['DRAFT', 'VALIDATED', 'BLOCKED', 'OUTDATED'];
export const ENVELOPE_REQUIRED = ['id', 'schemaVersion', 'createdAt', 'updatedAt', 'status', 'source', 'links'];

/**
 * Validate an artefact JSON against its schema.
 * Returns { valid, errors, warnings }
 */
export async function validateArtefact(json, options = {}) {
  const errors = [];
  const warnings = [];
  
  // Check required envelope fields
  for (const field of ENVELOPE_REQUIRED) {
    if (!(field in json)) {
      errors.push(`Missing required field: ${field}`);
    }
  }
  
  // Validate schemaVersion format
  if (json.schemaVersion && typeof json.schemaVersion !== 'string') {
    errors.push('schemaVersion must be a string');
  }
  
  // Validate id format
  if (json.id && typeof json.id !== 'string') {
    errors.push('id must be a string');
  }
  
  // Validate status
  if (json.status && !ARTIFACT_STATUSES.includes(json.status)) {
    errors.push(`Invalid status: ${json.status}. Must be one of: ${ARTIFACT_STATUSES.join(', ')}`);
  }
  
  // Validate source
  if (json.source) {
    if (typeof json.source !== 'object') {
      errors.push('source must be an object');
    } else {
      if (!json.source.type || !['human', 'generated', 'derived'].includes(json.source.type)) {
        errors.push('source.type must be one of: human, generated, derived');
      }
      if (!json.source.author || typeof json.source.author !== 'string') {
        errors.push('source.author must be a string');
      }
      if (!json.source.createdAt || !isValidIsoDate(json.source.createdAt)) {
        errors.push('source.createdAt must be a valid ISO-8601 date-time');
      }
    }
  }
  
  // Validate links
  if (json.links && typeof json.links !== 'object') {
    errors.push('links must be an object');
  }
  
  // Validate validation object
  if (json.validation) {
    if (typeof json.validation !== 'object') {
      errors.push('validation must be an object');
    } else {
      if (!json.validation.schemaVersion || typeof json.validation.schemaVersion !== 'string') {
        errors.push('validation.schemaVersion must be a string');
      }
      if (!isValidIsoDate(json.validation.timestamp)) {
        errors.push('validation.timestamp must be a valid ISO-8601 date-time');
      }
      if (typeof json.validation.valid !== 'boolean') {
        errors.push('validation.valid must be a boolean');
      }
      if (json.validation.errors && !Array.isArray(json.validation.errors)) {
        errors.push('validation.errors must be an array');
      }
      if (json.validation.warnings && !Array.isArray(json.validation.warnings)) {
        errors.push('validation.warnings must be an array');
      }
    }
  }
  
  return { valid: errors.length === 0, errors, warnings };
}

/**
 * Resolve links and verify they point to existing artefacts.
 * Returns { resolved, unresolved, errors }
 */
export async function resolveLinks(root, links, options = {}) {
  const unresolved = [];
  const errors = [];
  
  if (!links || typeof links !== 'object') {
    return { resolved: [], unresolved: [], errors: ['links must be an object'] };
  }
  
  // Get all artefact IDs in the artefacts directory
  const artefactIds = await getAllArtefactIds(root);
  
  // Check each link
  const linkTypes = ['implements', 'designedBy', 'implementedBy', 'verifiedBy', 'evidencedBy'];
  for (const linkType of linkTypes) {
    const targetIds = links[linkType];
    if (!targetIds || !Array.isArray(targetIds)) continue;
    
    for (const targetId of targetIds) {
      if (!artefactIds.has(targetId)) {
        unresolved.push({ type: linkType, targetId });
        errors.push(`Unresolved link: ${linkType} -> ${targetId} (not found in artefacts)`);
      }
    }
  }
  
  return { resolved: unresolved.length === 0 ? Object.keys(links).length : 0, unresolved, errors };
}

/**
 * Get all artefact IDs from the artefacts directory.
 */
export async function getAllArtefactIds(root) {
  const ids = new Set();
  const { aidfPaths } = await import('../paths.mjs');
  const { readDirSafe } = await import('../../util/fsx.mjs');
  
  const artefactsDir = aidfPaths(root).artefacts.dir;
  if (!await exists(artefactsDir)) {
    return ids;
  }
  
  try {
    const { collectArtefactFiles } = await import('../traceability/chain.mjs');
    const files = await collectArtefactFiles(root);
    for (const { id } of files) {
      ids.add(id);
    }
  } catch {
    // Directory doesn't exist or can't be read
  }
  
  return ids;
}

/**
 * Check if a string is a valid ISO-8601 date-time.
 */
function isValidIsoDate(str) {
  if (typeof str !== 'string') return false;
  const date = new Date(str);
  return !isNaN(date.getTime()) && str.includes('T');
}

/**
 * Calculate SHA-256 hash of an artefact body (excluding hash field if present).
 */
export function hashArtefact(body) {
  const copy = { ...body };
  delete copy.hash;
  delete copy.validation;
  return sha256Text(JSON.stringify(copy, null, 2));
}