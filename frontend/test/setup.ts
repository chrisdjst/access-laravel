import '@testing-library/jest-dom/vitest';
import { afterAll, afterEach, beforeAll } from 'vitest';

import { server } from './server.js';

// Boot the mock API once per test process.
beforeAll(() => {
  server.listen({ onUnhandledRequest: 'error' });
});

// Reset handlers between tests so per-test overrides don't leak.
afterEach(() => {
  server.resetHandlers();
});

afterAll(() => {
  server.close();
});
