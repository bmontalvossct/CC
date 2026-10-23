import { createHash } from 'node:crypto';
import { execFileSync } from 'node:child_process';
import { mkdir, readFile, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

// Official PHP Windows x64 NTS release, verified against php.net/downloads.php.
const version = '8.5.12';
const sha256 = '2ab242af38d76bdf417cde5ec048b1bcd76a5a40f39a3d15d5f046f38571c477';
const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const runtime = path.join(root, 'scratch', 'runtime', `php-${version}`);
const archive = path.join(root, 'scratch', `php-${version}.zip`);
await mkdir(path.dirname(archive), { recursive: true });
const response = await fetch(`https://downloads.php.net/~windows/releases/archives/php-${version}-nts-Win32-vs17-x64.zip`);
if (!response.ok) throw new Error(`PHP download failed: ${response.status}`);
const bytes = Buffer.from(await response.arrayBuffer());
if (createHash('sha256').update(bytes).digest('hex') !== sha256) throw new Error('PHP archive checksum mismatch');
await writeFile(archive, bytes);
const quote = (value) => `'${value.replaceAll("'", "''")}'`;
execFileSync('powershell.exe', ['-NoProfile', '-NonInteractive', '-Command',
    `Expand-Archive -LiteralPath ${quote(archive)} -DestinationPath ${quote(runtime)} -Force`], { windowsHide: true });
let ini = await readFile(path.join(runtime, 'php.ini-production'), 'utf8');
ini = ini.replace(';extension_dir = "ext"', `extension_dir = "${path.join(runtime, 'ext').replaceAll('\\', '/')}"`);
for (const extension of ['curl', 'fileinfo', 'gd', 'intl', 'mbstring', 'openssl', 'pdo_sqlite', 'pdo_pgsql', 'sqlite3', 'zip']) {
    ini = ini.replace(`;extension=${extension}`, `extension=${extension}`);
}
await writeFile(path.join(runtime, 'php.ini'), `${ini}\ndate.timezone=Asia/Manila\n`);
execFileSync(path.join(runtime, 'php.exe'), ['-v'], { stdio: 'inherit', windowsHide: true });
console.log(`Installed ${runtime}. Use .\\php.cmd for this project's PHP commands.`);
