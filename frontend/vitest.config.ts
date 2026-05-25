import { defineConfig } from 'vitest/config';

export default defineConfig({
  test: {
    environment: 'jsdom',
    globals: false,
    setupFiles: ['./test/setup.ts'],
    include: ['src/**/*.test.{ts,tsx}'],
    coverage: {
      provider: 'v8',
      include: ['src/**/*.{ts,tsx}'],
      exclude: ['src/**/*.test.{ts,tsx}', 'src/version.ts'],
      reporter: ['text', 'html'],
      thresholds: {
        // Hooks are the package's surface — exercise every code
        // path. Bump after F7-F11 ship components with their own
        // coverage targets.
        statements: 80,
        branches: 70,
        functions: 80,
        lines: 80,
      },
    },
  },
});
