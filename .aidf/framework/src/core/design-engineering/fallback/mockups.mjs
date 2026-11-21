/**
 * L3: Fallback Mockup Generation
 * Generates CSS-based mockups when Figma is unavailable.
 */

/**
 * Generate CSS mockup based on theme configuration.
 */
export function generateCSSMockup(theme) {
  const { colors = {}, typography = {}, components = {} } = theme;
  
  const colorVars = Object.entries(colors).map(([name, value]) => `  --color-${name}: ${value};`).join('\n');
  const fontVars = Object.entries(typography).map(([name, value]) => `  --font-${name}: ${value};`).join('\n');
  
  return {
    type: 'css',
    name: 'Design System - CSS Mockup',
    css: `
/* Design System - CSS Mockup */
:root {
${colorVars}
${fontVars}
}

/* Reset */
* {
  box-sizing: border-box;
  margin: 0;
  padding: 0;
}

body {
  font-family: var(--font-body, sans-serif);
  font-size: var(--font-size-base, 16px);
  line-height: var(--font-line-height, 1.5);
  color: var(--color-text, #333);
  background-color: var(--color-bg, #fff);
}

/* Component: Button */
.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: var(--spacing-sm, 12px) var(--spacing-md, 24px);
  border-radius: var(--border-radius-sm, 4px);
  font-weight: 500;
  font-size: var(--font-size-base, 14px);
  cursor: pointer;
  border: none;
  transition: all 0.2s ease;
}

.btn-primary {
  background-color: var(--color-primary, #0066cc);
  color: var(--color-primary-text, #fff);
}

.btn-primary:hover {
  background-color: var(--color-primary-hover, #0052a3);
}

.btn-secondary {
  background-color: var(--color-secondary, #6c757d);
  color: var(--color-secondary-text, #fff);
}

.btn-secondary:hover {
  background-color: var(--color-secondary-hover, #5a6268);
}

/* Component: Input */
.input {
  display: block;
  width: 100%;
  padding: var(--spacing-sm, 12px);
  border: 1px solid var(--color-border, #ddd);
  border-radius: var(--border-radius-sm, 4px);
  font-size: var(--font-size-base, 14px);
  transition: border-color 0.2s;
}

.input:focus {
  outline: none;
  border-color: var(--color-primary, #0066cc);
}

.input::placeholder {
  color: var(--color-placeholder, #999);
}

/* Component: Card */
.card {
  background: var(--color-bg, #fff);
  border: 1px solid var(--color-border, #ddd);
  border-radius: var(--border-radius-md, 8px);
  padding: var(--spacing-md, 20px);
  box-shadow: var(--shadow-sm, 0 2px 4px rgba(0,0,0,0.05));
}

.card-header {
  margin-bottom: var(--spacing-sm, 12px);
}

.card-title {
  font-size: var(--font-size-lg, 18px);
  font-weight: 600;
  margin-bottom: var(--spacing-xs, 4px);
}

.card-body {
  font-size: var(--font-size-base, 14px);
  color: var(--color-text-secondary, #666);
}

/* Component: Navigation */
.nav {
  display: flex;
  gap: var(--spacing-sm, 16px);
  padding: var(--spacing-md, 20px);
  background-color: var(--color-bg, #fff);
  border-bottom: 1px solid var(--color-border, #ddd);
}

.nav-item {
  text-decoration: none;
  color: var(--color-text, #333);
  font-weight: 500;
  padding: var(--spacing-xs, 8px) var(--spacing-sm, 12px);
  border-radius: var(--border-radius-sm, 4px);
  transition: background-color 0.2s;
}

.nav-item:hover {
  background-color: var(--color-bg-secondary, #f5f5f5);
}

/* Component: Badge */
.badge {
  display: inline-flex;
  align-items: center;
  padding: 2px 8px;
  border-radius: var(--border-radius-pill, 12px);
  font-size: 12px;
  font-weight: 500;
}

.badge-primary {
  background-color: var(--color-primary, #0066cc);
  color: var(--color-primary-text, #fff);
}

.badge-success {
  background-color: var(--color-success, #28a745);
  color: var(--color-success-text, #fff);
}

.badge-warning {
  background-color: var(--color-warning, #ffc107);
  color: var(--color-warning-text, #333);
}

.badge-danger {
  background-color: var(--color-danger, #dc3545);
  color: var(--color-danger-text, #fff);
}

/* Utilities */
.text-center { text-align: center; }
.text-right { text-align: right; }
.mt-sm { margin-top: var(--spacing-xs, 8px); }
.mt-md { margin-top: var(--spacing-sm, 12px); }
.mt-lg { margin-top: var(--spacing-md, 20px); }
.mt-xl { margin-top: var(--spacing-lg, 32px); }
.py-sm { padding-top: var(--spacing-xs, 8px); padding-bottom: var(--spacing-xs, 8px); }
.py-md { padding-top: var(--spacing-sm, 12px); padding-bottom: var(--spacing-sm, 12px); }
.py-lg { padding-top: var(--spacing-md, 20px); padding-bottom: var(--spacing-md, 20px); }
.py-xl { padding-top: var(--spacing-lg, 32px); padding-bottom: var(--spacing-lg, 32px); }

/* Responsive */
@media (max-width: 768px) {
  .nav {
    flex-direction: column;
    gap: var(--spacing-xs, 8px);
  }
}
    `.trim(),
    timestamp: new Date().toISOString()
  };
}

/**
 * Generate responsive CSS with media queries.
 */
export function generateResponsiveCSS(baseCSS, breakpoints = {
  mobile: 375,
  tablet: 768,
  desktop: 1024,
  large: 1440
}) {
  return `
${baseCSS}

/* Responsive Utilities */
@media (max-width: ${breakpoints.mobile}px) {
  .container { padding: 0 var(--spacing-sm, 16px); }
}

@media (min-width: ${breakpoints.tablet}px) {
  .container { max-width: ${breakpoints.tablet}px; }
}

@media (min-width: ${breakpoints.desktop}px) {
  .container { max-width: ${breakpoints.desktop}px; }
}

@media (min-width: ${breakpoints.large}px) {
  .container { max-width: ${breakpoints.large}px; }
}
    `.trim();
}
