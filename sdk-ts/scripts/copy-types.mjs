#!/usr/bin/env node
/**
 * Copies the generated `src/types.d.ts` into `dist/` so the
 * package.json `exports['./types']` map resolves at runtime.
 *
 * TypeScript's compiler does not emit pure `.d.ts` source files to
 * the output directory — only `.ts`/`.tsx` that produce both .js and
 * .d.ts. Our types.d.ts is hand-written by openapi-typescript and
 * carries no runtime, so we copy it explicitly.
 */

import { copyFile, mkdir } from 'node:fs/promises';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const here = dirname(fileURLToPath(import.meta.url));
const src = resolve(here, '..', 'src', 'types.d.ts');
const dst = resolve(here, '..', 'dist', 'types.d.ts');

await mkdir(dirname(dst), { recursive: true });
await copyFile(src, dst);

console.log(`✓ Copied ${src} → ${dst}`);
