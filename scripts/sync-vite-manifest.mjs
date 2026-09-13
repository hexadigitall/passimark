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
