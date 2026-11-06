// Copies only the Lucide icons the app actually uses into app/icons
// (rendered inline by the PHP icon() helper - no icon font / CDN download).
// Any quoted name on a line that calls icon(...) or declares 'icon' => '...' is collected.
import { readFileSync, writeFileSync, readdirSync, statSync, mkdirSync, existsSync } from 'node:fs';
import { join, extname } from 'node:path';

const root = new URL('..', import.meta.url).pathname;
const src = join(root, 'node_modules/lucide-static/icons');
const dest = join(root, 'app/icons');
const scan = ['views', 'app', 'config', 'public/assets/js'];
const names = new Set(['circle-help']);

const walk = (dir) => {
  for (const f of readdirSync(dir)) {
    const p = join(dir, f);
    if (statSync(p).isDirectory()) { if (f !== 'icons') walk(p); continue; }
    if (!['.php', '.js'].includes(extname(p))) continue;
    for (const line of readFileSync(p, 'utf8').split('\n')) {
      if (!/icon\(|'icon'\s*=>|data-icon=/.test(line)) continue;
      for (const m of line.matchAll(/['"]([a-z0-9]+(?:-[a-z0-9]+)*)['"]/g)) {
        if (existsSync(join(src, `${m[1]}.svg`))) names.add(m[1]);
      }
    }
  }
};
scan.forEach((d) => existsSync(join(root, d)) && walk(join(root, d)));
mkdirSync(dest, { recursive: true });
for (const n of names) {
  const svg = readFileSync(join(src, `${n}.svg`), 'utf8')
    .replace(/<!--[\s\S]*?-->/g, '')
    .replace(/\sclass="[^"]*"/, '')
    .replace(/\s(width|height)="24"/g, '')
    .replace(/\s*\n\s*/g, ' ')
    .replace(/>\s+</g, '><')
    .trim();
  writeFileSync(join(dest, `${n}.svg`), svg);
}
console.log(`icons: ${names.size} copied to app/icons`);
