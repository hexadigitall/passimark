import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const source = path.join(root, 'public', 'build', '.vite', 'manifest.json');
const target = path.join(root, 'public', 'build', 'manifest.json');

if (!fs.existsSync(source)) {
  console.warn(`Vite manifest not found at ${source}`);
  process.exit(0);
}

fs.copyFileSync(source, target);
console.log(`Copied Vite manifest to ${target}`);

// Generate native client HTML shell (for Tauri Desktop and Capacitor Mobile)
try {
  const manifestData = JSON.parse(fs.readFileSync(target, 'utf8'));
  
  // Find native entrypoint
  const nativeEntry = manifestData['resources/js/native.jsx'] || manifestData['native'];
  const appEntry = manifestData['resources/js/app.jsx'] || manifestData['app'];

  let nativeJs = nativeEntry?.file || 'assets/native.js';
  let cssFiles = [];

  for (const entry of Object.values(manifestData)) {
    if (entry.css && Array.isArray(entry.css)) {
      cssFiles.push(...entry.css);
    }
    if (entry.file && entry.file.endsWith('.css')) {
      cssFiles.push(entry.file);
    }
  }

  // Deduplicate css files
  cssFiles = [...new Set(cssFiles)];

  const cssTags = cssFiles
    .map((css) => `    <link rel="stylesheet" href="./build/${css}">`)
    .join('\n');

  const nativeIndexHtml = `<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#030712">
    <title>Passimark Desktop & Mobile</title>
    <link rel="icon" href="./images/passimark/passimark_favicon.ico">
    <link rel="preload" href="./fonts/inter-latin-wght-normal.woff2" as="font" type="font/woff2" crossorigin>
${cssTags}
    <style>
      body {
        margin: 0;
        padding: 0;
        background-color: #030712;
        color: #f8fafc;
        font-family: Inter, ui-sans-serif, system-ui, -apple-system, sans-serif;
        overflow: hidden;
        user-select: none;
      }
      #native-app {
        height: 100vh;
        width: 100vw;
        display: flex;
        flex-direction: column;
      }
    </style>
  </head>
  <body class="bg-slate-950 text-slate-100 antialiased">
    <div id="native-app">
      <div style="display:flex;height:100vh;width:100vw;align-items:center;justify-content:center;background:#030712;flex-direction:column;gap:16px;">
        <div style="height:36px;width:36px;border:3px solid #1e293b;border-top-color:#22c55e;border-radius:50%;animation:spin 1s linear infinite;"></div>
        <p style="font-size:13px;color:#94a3b8;font-weight:500;">Loading Passimark Native Shell...</p>
      </div>
    </div>
    <script type="module" src="./build/${nativeJs}"></script>
    <style>
      @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    </style>
  </body>
</html>`;

  const indexPath = path.join(root, 'public', 'index.html');
  fs.writeFileSync(indexPath, nativeIndexHtml, 'utf8');
  console.log(`Generated native client shell at ${indexPath}`);
} catch (err) {
  console.error('Failed to generate native client HTML shell:', err);
}
