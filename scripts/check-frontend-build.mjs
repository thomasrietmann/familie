import { execFileSync } from 'node:child_process';

const changes = execFileSync('git', [
  'status', '--porcelain', '--untracked-files=all', '--', 'public/build',
], { encoding: 'utf8' });

if (changes.trim()) {
  console.error('Frontend-Build stimmt nicht mit dem eingecheckten Stand überein.');
  console.error(changes);
  console.error('npm run build ausführen und public/build/ zusammen mit den Quellen committen.');
  process.exitCode = 1;
} else {
  console.log('Frontend-Build entspricht dem eingecheckten Stand.');
}
