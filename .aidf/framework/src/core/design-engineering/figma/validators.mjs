/**
 * L3: Figma Validation Functions
 * Real Figma REST API checks (api.figma.com). The token comes from
 * figmaConfig.apiKey / figmaConfig.token or the FIGMA_TOKEN environment
 * variable; it is sent only in the X-Figma-Token header and never logged.
 */

const FIGMA_API = 'https://api.figma.com/v1';

function resolveToken(figmaConfig) {
  return figmaConfig?.apiKey ?? figmaConfig?.token ?? process.env.FIGMA_TOKEN ?? null;
}

async function figmaGet(pathname, token) {
  const response = await fetch(`${FIGMA_API}${pathname}`, {
    headers: { 'X-Figma-Token': token },
    signal: AbortSignal.timeout(15000),
  });
  if (response.status === 401 || response.status === 403) {
    const err = new Error(`Figma API ${response.status}: token rejected or insufficient scope.`);
    err.status = response.status;
    throw err;
  }
  if (response.status === 404) {
    const err = new Error('Figma API 404: resource not found.');
    err.status = 404;
    throw err;
  }
  if (!response.ok) {
    const err = new Error(`Figma API ${response.status}: ${response.statusText}`);
    err.status = response.status;
    throw err;
  }
  return response.json();
}

/**
 * Create a minimal real Figma client.
 */
function createFigmaClient(figmaConfig) {
  const token = resolveToken(figmaConfig);
  if (!token) {
    throw new Error('Figma token missing (figma.apiKey/token or FIGMA_TOKEN).');
  }
  return {
    testConnection: async () => {
      await figmaGet('/me', token);
    },
    getMe: async () => {
      const me = await figmaGet('/me', token);
      return {
        id: me.id,
        handle: me.handle,
        email: me.email ?? null,
        permissions: { read: true },
        role: me.seatType ?? null,
      };
    },
    getFile: async (fileId) => {
      const file = await figmaGet(`/files/${encodeURIComponent(fileId)}?depth=1`, token);
      return {
        id: fileId,
        name: file.name,
        owner: file.editorType ?? null,
        modified_at: file.lastModified,
      };
    },
  };
}

/**
 * Test Figma connection.
 */
export async function testConnection(figmaConfig) {
  try {
    const client = createFigmaClient(figmaConfig);
    await client.testConnection();
    return { status: 'connected', error: null };
  } catch (error) {
    return { status: 'error', error: error.message };
  }
}

/**
 * Verify Figma identity.
 */
export async function verifyIdentity(figmaConfig) {
  try {
    const client = createFigmaClient(figmaConfig);
    const me = await client.getMe();
    return {
      status: 'verified',
      identity: {
        id: me.id,
        handle: me.handle,
        email: me.email
      }
    };
  } catch (error) {
    return { status: 'error', error: error.message };
  }
}

/**
 * Check Figma permissions.
 * The REST API only exposes what the token can do; a successful /me call
 * proves the token is valid, and a successful file read proves read access.
 */
export async function checkPermissions(figmaConfig, requiredPermissions = ['read']) {
  try {
    const client = createFigmaClient(figmaConfig);
    await client.getMe();
    const missing = requiredPermissions.filter((p) => p !== 'read');
    return {
      status: missing.length === 0 ? 'authorized' : 'unauthorized',
      missing
    };
  } catch (error) {
    return { status: 'error', error: error.message };
  }
}

/**
 * Check file accessibility.
 */
export async function checkFile(figmaConfig, fileId) {
  try {
    const client = createFigmaClient(figmaConfig);
    const file = await client.getFile(fileId);

    return {
      status: 'accessible',
      fileId,
      name: file.name,
      owner: file.owner,
      lastModified: file.modified_at
    };
  } catch (error) {
    return {
      status: 'error',
      error: error.message,
      fileExists: error.status !== 404
    };
  }
}

/**
 * Validate Figma configuration for use.
 * Returns comprehensive validation result.
 */
export async function validateFigma(figmaConfig) {
  // Check if Figma is disabled
  if (!figmaConfig?.enabled) {
    return { status: 'disabled' };
  }

  // Token may come from config or FIGMA_TOKEN; fileId is still required
  // because validation proves access to a concrete design file.
  if (!resolveToken(figmaConfig) || !figmaConfig.fileId) {
    return { status: 'configuration_missing' };
  }

  // Test connection
  const connection = await testConnection(figmaConfig);
  if (connection.status !== 'connected') {
    return { status: 'connection_failed', error: connection.error };
  }

  // Verify identity
  const identity = await verifyIdentity(figmaConfig);
  if (identity.status !== 'verified') {
    return { status: 'identity_failed', error: identity.error };
  }

  // Check permissions
  const permissions = await checkPermissions(figmaConfig, ['read']);
  if (permissions.status !== 'authorized') {
    return {
      status: 'permissions_failed',
      missing: permissions.missing
    };
  }

  // Check file accessibility
  const file = await checkFile(figmaConfig, figmaConfig.fileId);
  if (file.status !== 'accessible') {
    return {
      status: 'file_failed',
      error: file.error,
      fileExists: file.fileExists
    };
  }

  // All checks passed
  return {
    status: 'fully_verified',
    identity: identity.identity
  };
}
