#!/usr/bin/env node
/**
 * Reads ../openapi.json and writes src/types.d.ts via openapi-typescript.
 *
 * Designed to be deterministic so the CI drift gate (sdk-ts-drift.yml)
 * can `git diff` the output against the committed file.
 */

import { mkdir, readFile, writeFile } from 'node:fs/promises';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

import openapiTS, { astToString } from 'openapi-typescript';

const here = dirname(fileURLToPath(import.meta.url));
const specPath = resolve(here, '..', '..', 'openapi.json');
const outPath = resolve(here, '..', 'src', 'types.d.ts');

const spec = JSON.parse(await readFile(specPath, 'utf8'));

const ast = await openapiTS(spec, {
  alphabetize: true,         // sort properties → stable output
  immutable: true,           // readonly fields in the generated types
  exportType: true,          // emit `export type` rather than `export interface`
  emptyObjectsUnknown: true, // `{}` → `Record<string, unknown>` instead of permissive `any`
});

const banner = `/**
 * AUTO-GENERATED — do not edit by hand.
 *
 * Source: ../openapi.json
 * Run    \`npm run generate\` (inside sdk-ts/) to regenerate.
 *
 * The CI workflow .github/workflows/sdk-ts-drift.yml fails on PRs
 * that change openapi.json without regenerating this file.
 */

`;

await mkdir(dirname(outPath), { recursive: true });
await writeFile(outPath, banner + astToString(ast), 'utf8');

console.log(`✓ Wrote ${outPath}`);
