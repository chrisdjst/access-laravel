import '@testing-library/jest-dom/vitest';
import { cleanup } from '@testing-library/react';
import { afterAll, afterEach, beforeAll } from 'vitest';

import { server } from './server.js';

// Boot the mock API once per test process.
beforeAll(() => {
  server.listen({ onUnhandledRequest: 'error' });
});

// Reset handlers between tests so per-test overrides don't leak,
// and unmount any React tree RTL left behind (Vitest disables RTL's
// auto-cleanup because the testing-library/react package can't detect
// the test runner from a non-jest env).
afterEach(() => {
  server.resetHandlers();
  cleanup();
});

afterAll(() => {
  server.close();
});
