import { aidfPaths, AIDF_VERSION } from '../paths.mjs';
import { readJson, writeJson, exists, readDirSafe } from '../../util/fsx.mjs';

export const LINK_TYPES = ['implements', 'designed-by', 'implemented-by', 'verified-by', 'evidenced-by'];

export const TRACEABILITY_TYPES = {
  requirement: 'requirement',
  design: 'design',
  implementation: 'implementation',
  test: 'test',
  evidence: 'evidence'
};

/**
 * Recursively collect artefact JSON files under the artefacts directory.
 * Returns [{ id, path, body }] for every parseable file exposing an `id`.
 */
export async function collectArtefactFiles(root) {
  const found = [];
  const artefactsDir = aidfPaths(root).artefacts.dir;
  if (!await exists(artefactsDir)) {
    return found;
  }

  async function walk(dir) {
    const entries = await readDirSafe(dir);
    for (const entry of entries) {
      const full = `${dir}/${entry.name}`;
      if (entry.isDirectory()) {
        await walk(full);
      } else if (entry.isFile() && entry.name.endsWith('.json') && entry.name !== 'index.json') {
        try {
          const body = await readJson(full, null);
          if (body && body.id) {
            found.push({ id: body.id, path: full, body });
          }
        } catch {
          // Skip malformed files
        }
      }
    }
  }

  await walk(artefactsDir);
  return found;
}

/**
 * Load all artefacts from the artefacts directory.
 */
export async function loadAllArtefacts(root) {
  const artefacts = new Map();
  for (const { id, body } of await collectArtefactFiles(root)) {
    artefacts.set(id, body);
  }
  return artefacts;
}

/**
 * Resolve the on-disk path of an artefact by ID.
 */
export async function artefactFilePath(root, artefactId) {
  const files = await collectArtefactFiles(root);
  const hit = files.find((f) => f.id === artefactId);
  return hit ? hit.path : null;
}

/**
 * Load a specific artefact by ID.
 */
export async function loadArtefact(root, artefactId) {
  const artefacts = await loadAllArtefacts(root);
  return artefacts.get(artefactId) || null;
}

/**
 * Resolve a link to its target artefact.
 */
export async function resolveLink(root, targetId) {
  const artefacts = await loadAllArtefacts(root);
  return artefacts.get(targetId) || null;
}

/**
 * Create a link between two artefacts.
 */
export async function createLink(root, sourceId, targetType, targetId, options = {}) {
  const artefacts = await loadAllArtefacts(root);
  
  const source = artefacts.get(sourceId);
  if (!source) {
    throw new Error(`Source artefact not found: ${sourceId}`);
  }
  
  const target = artefacts.get(targetId);
  if (!target) {
    throw new Error(`Target artefact not found: ${targetId}`);
  }
  
  // Determine link type based on target type
  const linkType = getLinkType(targetType, source.type);
  if (!linkType) {
    throw new Error(`Invalid link: ${source.type} -> ${targetType}`);
  }
  
  // Add link to source
  if (!source.links) {
    source.links = {};
  }
  if (!source.links[linkType]) {
    source.links[linkType] = [];
  }
  if (!source.links[linkType].includes(targetId)) {
    source.links[linkType].push(targetId);
  }
  
  // Add reverse link to target
  const reverseLinkType = getReverseLinkType(linkType);
  if (!target.links) {
    target.links = {};
  }
  if (!target.links[reverseLinkType]) {
    target.links[reverseLinkType] = [];
  }
  if (!target.links[reverseLinkType].includes(sourceId)) {
    target.links[reverseLinkType].push(sourceId);
  }
  
  // Update both artefacts
  const sourcePath = await artefactFilePath(root, sourceId);
  const targetPath = await artefactFilePath(root, targetId);
  if (!sourcePath || !targetPath) {
    throw new Error('Artefact file path could not be resolved.');
  }

  await writeJson(sourcePath, source);
  await writeJson(targetPath, target);
  
  return {
    source,
    target,
    linkType,
    reverseLinkType
  };
}

/**
 * Get the link type for a forward connection.
 */
function getLinkType(targetType, sourceType) {
  const linkMap = {
    requirement: {
      design: 'designed-by',
      implementation: 'implemented-by',
      test: 'verified-by',
      evidence: 'evidenced-by'
    },
    design: {
      requirement: 'implements',
      implementation: 'implemented-by',
      test: 'verified-by'
    },
    implementation: {
      requirement: 'implements',
      design: 'designed-by',
      test: 'verified-by'
    },
    test: {
      requirement: 'verifies',
      design: 'designs',
      implementation: 'implements'
    },
    evidence: {
      test: 'evidences',
      requirement: 'evidences'
    }
  };
  
  return linkMap[targetType]?.[sourceType] || null;
}

/**
 * Get the reverse link type.
 */
function getReverseLinkType(linkType) {
  const reverseMap = {
    'implements': 'implemented-by',
    'designed-by': 'designs',
    'implemented-by': 'implements',
    'verified-by': 'verifies',
    'evidenced-by': 'evidences',
    'verifies': 'verified-by',
    'designs': 'designed-by',
    'evidences': 'evidenced-by'
  };
  
  return reverseMap[linkType] || null;
}

/**
 * Verify a traceability chain for a given artefact.
 * Returns { ok, path, brokenLinks, coverage }
 */
export async function verifyChain(root, artefactId, options = {}) {
  const artefacts = await loadAllArtefacts(root);
  const start = artefacts.get(artefactId);
  
  if (!start) {
    return {
      ok: false,
      artefactId,
      brokenLinks: [`Artefact not found: ${artefactId}`],
      coverage: null
    };
  }
  
  const path = [artefactId];
  const brokenLinks = [];
  
  // Follow links of type: requirement -> design -> implementation -> test -> evidence
  let current = start;
  let maxDepth = options.maxDepth || 5;
  let depth = 0;
  
  while (depth < maxDepth && current.links) {
    // Try to find the next link type
    const nextId = current.links.implementedBy?.[0] || 
                   current.links.verifiedBy?.[0] || 
                   current.links.evidencedBy?.[0] ||
                   current.links.designedBy?.[0] ||
                   current.links.implements?.[0];
    
    if (!nextId) break;
    
    const next = await resolveLink(root, nextId);
    if (!next) {
      brokenLinks.push(`Missing link: ${current.id} -> ${nextId}`);
      break;
    }
    
    path.push(nextId);
    current = next;
    depth++;
  }
  
  // Calculate coverage based on path
  const coverage = calculateCoverage(path, artefacts);
  
  return {
    ok: brokenLinks.length === 0,
    artefactId,
    path,
    brokenLinks,
    coverage
  };
}

/**
 * Find all broken links in the artefacts directory.
 */
export async function findBrokenLinks(root, options = {}) {
  const artefacts = await loadAllArtefacts(root);
  const broken = [];
  
  for (const [id, artefact] of artefacts) {
    if (!artefact.links) continue;
    
    const linkTypes = ['implements', 'designedBy', 'implementedBy', 'verifiedBy', 'evidencedBy'];
    for (const linkType of linkTypes) {
      const targetIds = artefact.links[linkType];
      if (!targetIds || !Array.isArray(targetIds)) continue;
      
      for (const targetId of targetIds) {
        if (!artefacts.has(targetId)) {
          broken.push({
            sourceId: id,
            linkType,
            targetId,
            reason: `Target artefact not found: ${targetId}`
          });
        }
      }
    }
  }
  
  return broken;
}

/**
 * Find artefacts without a specific type of link.
 */
const TYPE_ID_PREFIX = {
  requirement: 'REQ-',
  design: 'DES-',
  implementation: 'BACKEND-',
  test: 'TEST-',
  evidence: 'EVID-',
};

function artefactMatchesType(artefact, type) {
  if (!artefact) return false;
  if (artefact.type === type) return true;
  const prefix = TYPE_ID_PREFIX[type];
  return Boolean(prefix && typeof artefact.id === 'string' && artefact.id.startsWith(prefix));
}

export async function findMissingLinks(root, options = {}) {
  const artefacts = await loadAllArtefacts(root);
  const missing = [];

  const targetType = options.type;
  if (!targetType) {
    throw new Error('Missing --type option (requirement, design, implementation, test, evidence)');
  }

  for (const [id, artefact] of artefacts) {
    const links = artefact.links || {};
    let hasLink = false;
    for (const targetIds of Object.values(links)) {
      if (!Array.isArray(targetIds)) continue;
      for (const targetId of targetIds) {
        if (artefactMatchesType(artefacts.get(targetId), targetType)) {
          hasLink = true;
          break;
        }
      }
      if (hasLink) break;
    }

    if (!hasLink) {
      missing.push({
        id,
        type: artefact.type || 'unknown',
        missing: targetType
      });
    }
  }

  return missing;
}

/**
 * Calculate coverage for a path of artefacts.
 */
function calculateCoverage(path, artefacts) {
  if (path.length === 0) return null;
  
  const start = artefacts.get(path[0]);
  if (!start || start.type !== 'requirement') return null;
  
  const criteriaCount = start.acceptanceCriteria?.length || 0;
  const hasTest = path.some(id => artefacts.get(id)?.type === 'test');
  const hasEvidence = path.some(id => artefacts.get(id)?.type === 'evidence');
  
  return {
    requirementId: path[0],
    acceptanceCriteria: criteriaCount,
    hasImplementation: path.some(id => artefacts.get(id)?.type === 'implementation'),
    hasTest,
    hasEvidence,
    isFullyTraced: criteriaCount > 0 && hasTest && hasEvidence
  };
}

/**
 * Generate a traceability report for a project.
 * Returns comprehensive coverage metrics and visualizations.
 */
export async function generateTraceabilityReport(root, options = {}) {
  const artefacts = await loadAllArtefacts(root);
  const report = {
    summary: {
      totalArtefacts: artefacts.size,
      byType: {},
      byStatus: {}
    },
    coverage: {
      requirements: [],
      overall: {
        totalRequirements: 0,
        fullyTraced: 0,
        partiallyTraced: 0,
        notTraced: 0,
        coveragePercentage: 0
      }
    },
    links: {
      total: 0,
      byType: {}
    },
    broken: await findBrokenLinks(root),
    missing: await findMissingLinks(root, { type: 'test' })
  };
  
  // Count by type and status
  for (const artefact of artefacts.values()) {
    const type = artefact.type || 'unknown';
    const status = artefact.status || 'DRAFT';
    
    report.summary.byType[type] = (report.summary.byType[type] || 0) + 1;
    report.summary.byStatus[status] = (report.summary.byStatus[status] || 0) + 1;
    
    // Count links
    if (artefact.links) {
      for (const [linkType, targets] of Object.entries(artefact.links)) {
        if (Array.isArray(targets)) {
          report.links.total += targets.length;
          report.links.byType[linkType] = (report.links.byType[linkType] || 0) + targets.length;
        }
      }
    }
  }
  
  // Calculate requirement coverage
  let totalRequirements = 0;
  let fullyTraced = 0;
  let partiallyTraced = 0;
  let notTraced = 0;
  
  for (const [id, artefact] of artefacts) {
    if (artefact.type === 'requirement') {
      totalRequirements++;
      
      const path = [id];
      let current = artefact;
      let depth = 0;
      let hasTest = false;
      let hasEvidence = false;
      
      while (depth < 5 && current.links) {
        const nextId = current.links.implementedBy?.[0] || 
                       current.links.verifiedBy?.[0] ||
                       current.links.evidencedBy?.[0];
        
        if (!nextId) break;
        
        const next = artefacts.get(nextId);
        if (!next) break;
        
        path.push(nextId);
        if (next.type === 'test') hasTest = true;
        if (next.type === 'evidence') hasEvidence = true;
        
        current = next;
        depth++;
      }
      
      const criteriaCount = artefact.acceptanceCriteria?.length || 0;
      const isFullyTraced = criteriaCount > 0 && hasTest && hasEvidence;
      const isTraced = hasTest || hasEvidence;
      
      if (isFullyTraced) fullyTraced++;
      else if (isTraced) partiallyTraced++;
      else notTraced++;
      
      report.coverage.requirements.push({
        requirementId: id,
        acceptanceCriteria: criteriaCount,
        path,
        hasTest,
        hasEvidence,
        isFullyTraced
      });
    }
  }
  
  report.coverage.overall = {
    totalRequirements,
    fullyTraced,
    partiallyTraced,
    notTraced,
    coveragePercentage: totalRequirements > 0 ? Math.round((fullyTraced / totalRequirements) * 100) : 0
  };
  
  // Add recommendations
  report.recommendations = [];
  if (report.coverage.overall.notTraced > 0) {
    report.recommendations.push(`Add tests and evidence for ${report.coverage.overall.notTraced} untraced requirement(s).`);
  }
  if (report.links.total === 0) {
    report.recommendations.push('Add link relationships between artefacts to enable traceability.');
  }
  if (report.broken.length > 0) {
    report.recommendations.push(`Fix ${report.broken.length} broken link(s) to ensure complete traceability.`);
  }
  
  return report;
}
