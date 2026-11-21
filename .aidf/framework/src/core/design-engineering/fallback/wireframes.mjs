/**
 * L3: Fallback Wireframe Generation
 * Generates HTML/SVG wireframes when Figma is unavailable.
 */

/**
 * Generate SVG wireframe.
 */
export function generateSVGWireframe(screenConfig) {
  const { width = 375, height = 667, elements = [], name = 'Untitled' } = screenConfig;
  
  const svgElements = elements.map((el, index) => {
    const attrs = {
      x: el.x,
      y: el.y,
      width: el.width,
      height: el.height,
      fill: el.fill || 'none',
      stroke: el.stroke || '#ddd',
      'stroke-width': el.strokeWidth || 1
    };
    
    switch (el.type) {
      case 'text':
        return `<text x="${el.x}" y="${el.y}" font-family="sans-serif" font-size="${el.fontSize || 14}" fill="${el.color || '#333'}">${escapeXml(el.content)}</text>`;
      case 'button':
        return `<rect x="${el.x}" y="${el.y}" width="${el.width}" height="${el.height}" fill="${el.fill || 'none'}" stroke="${el.stroke || '#ddd'}" stroke-width="${el.strokeWidth || 1}" rx="4" /><text x="${el.x + el.width/2}" y="${el.y + el.height/2 + 4}" text-anchor="middle" font-size="14" fill="#333">${escapeXml(el.label)}</text>`;
      case 'input':
        return `<rect x="${el.x}" y="${el.y}" width="${el.width}" height="${el.height}" fill="${el.fill || 'none'}" stroke="${el.stroke || '#ddd'}" stroke-width="${el.strokeWidth || 1}" rx="2" /><text x="${el.x + 8}" y="${el.y + el.height/2 + 4}" font-size="12" fill="#999">${escapeXml(el.placeholder || 'Input')}</text>`;
      case 'image':
        return `<rect x="${el.x}" y="${el.y}" width="${el.width}" height="${el.height}" fill="#eee" stroke="#ddd" stroke-dasharray="4,4" /><text x="${el.x + el.width/2}" y="${el.y + el.height/2}" text-anchor="middle" font-size="10" fill="#999">[Image]</text>`;
      case 'card':
        return `<rect x="${el.x}" y="${el.y}" width="${el.width}" height="${el.height}" fill="${el.fill || 'none'}" stroke="${el.stroke || '#ddd'}" stroke-width="${el.strokeWidth || 1}" rx="4" />`;
      default:
        return `<rect x="${el.x}" y="${el.y}" width="${el.width}" height="${el.height}" fill="${el.fill || 'none'}" stroke="${el.stroke || '#ddd'}" stroke-width="${el.strokeWidth || 1}" />`;
    }
  });
  
  return {
    type: 'svg',
    name,
    width,
    height,
    svg: `
<svg width="${width}" height="${height}" xmlns="http://www.w3.org/2000/svg">
  <!-- Wireframe: ${escapeXml(name)} -->
  ${svgElements.join('\n  ')}
</svg>
    `.trim(),
    timestamp: new Date().toISOString()
  };
}

/**
 * Generate HTML wireframe.
 */
export function generateHTMLWireframe(screenConfig) {
  const { elements = [], name = 'Untitled', width = 375, height = 667 } = screenConfig;
  
  const htmlElements = elements.map((el, index) => {
    switch (el.type) {
      case 'text':
        return `<p style="font-size: ${el.fontSize || 14}px; margin: ${el.y}px 0 0 ${el.x}px;">${escapeXml(el.content)}</p>`;
      case 'button':
        return `<button style="position: absolute; left: ${el.x}px; top: ${el.y}px; width: ${el.width}px; height: ${el.height}px; border: 1px solid #ddd; border-radius: 4px; background: #f5f5f5;">${escapeXml(el.label)}</button>`;
      case 'input':
        return `<input type="text" placeholder="${escapeXml(el.placeholder || 'Input')}" style="position: absolute; left: ${el.x}px; top: ${el.y}px; width: ${el.width}px; height: ${el.height}px; border: 1px solid #ddd; border-radius: 2px; padding: 8px; box-sizing: border-box;" />`;
      case 'image':
        return `<div style="position: absolute; left: ${el.x}px; top: ${el.y}px; width: ${el.width}px; height: ${el.height}px; border: 1px dashed #ddd; background: #eee; display: flex; align-items: center; justify-content: center; color: #999; font-size: 10px;">[Image]</div>`;
      case 'card':
        return `<div style="position: absolute; left: ${el.x}px; top: ${el.y}px; width: ${el.width}px; height: ${el.height}px; border: 1px solid #ddd; background: #fff; border-radius: 8px; padding: 16px;"></div>`;
      default:
        return `<div style="position: absolute; left: ${el.x}px; top: ${el.y}px; width: ${el.width}px; height: ${el.height}px; border: 1px solid #ddd; background: #f9f9f9;"></div>`;
    }
  });
  
  return {
    type: 'html',
    name,
    html: `
<!DOCTYPE html>
<html>
<head>
  <title>${escapeXml(name)}</title>
  <style>
    body { margin: 0; padding: 20px; font-family: sans-serif; background: #fff; }
    .wireframe-container { position: relative; width: ${width || 375}px; height: ${height || 667}px; border: 1px solid #ddd; background: #fafafa; }
    h1 { color: #333; margin-bottom: 20px; }
  </style>
</head>
<body>
  <h1>${escapeXml(name)}</h1>
  <div class="wireframe-container">
    ${htmlElements.join('\n    ')}
  </div>
</body>
</html>
    `.trim(),
    timestamp: new Date().toISOString()
  };
}

/**
 * Escape XML special characters.
 */
function escapeXml(str) {
  if (!str) return '';
  return str
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&apos;');
}
