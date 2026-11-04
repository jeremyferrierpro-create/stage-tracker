import { QoderAdapter } from './qoder.mjs';
import { GenericAdapter } from './generic.mjs';

const FACTORIES = {
  qoder: () => new QoderAdapter(),
  generic: () => new GenericAdapter(),
};

export const ADAPTER_KEYS = Object.keys(FACTORIES);

export function getAdapter(key) {
  const factory = FACTORIES[key];
  if (!factory) throw new Error(`Unknown adapter "${key}". Available: ${ADAPTER_KEYS.join(', ')}`);
  return factory();
}

export function listAdapters() {
  return ADAPTER_KEYS.map((key) => {
    const adapter = getAdapter(key);
    return { key, name: adapter.name, capabilities: adapter.capabilities };
  });
}
