import { http, HttpResponse } from 'msw';
import { describe, expect, it } from 'vitest';

import { server } from '../../test/server.js';
import { createRbacApi } from './rbac.js';

describe('createRbacApi', () => {
  it('returns an openapi-fetch-shaped client with GET/POST/PUT/DELETE', () => {
    const api = createRbacApi({ baseUrl: 'http://app.test/api/admin' });

    expect(typeof api.GET).toBe('function');
    expect(typeof api.POST).toBe('function');
    expect(typeof api.PUT).toBe('function');
    expect(typeof api.DELETE).toBe('function');
  });

  it('attaches Accept + Access-Api-Version headers by default', async () => {
    let captured: Headers | null = null;
    server.use(
      http.get('http://app.test/api/admin/modules', ({ request }) => {
        captured = request.headers;

        return HttpResponse.json({ data: [], meta: { count: 0 } });
      }),
    );

    const api = createRbacApi({ baseUrl: 'http://app.test/api/admin' });
    await api.GET('/modules');

    expect(captured!.get('Accept')).toBe('application/json');
    expect(captured!.get('Access-Api-Version')).toBe('1');
  });

  it('forwards caller headers (Authorization, custom keys) without clobbering defaults', async () => {
    let captured: Headers | null = null;
    server.use(
      http.get('http://app.test/api/admin/modules', ({ request }) => {
        captured = request.headers;

        return HttpResponse.json({ data: [], meta: { count: 0 } });
      }),
    );

    const api = createRbacApi({
      baseUrl: 'http://app.test/api/admin',
      headers: { Authorization: 'Bearer token-xyz', 'X-Custom': 'hi' },
    });
    await api.GET('/modules');

    expect(captured!.get('Authorization')).toBe('Bearer token-xyz');
    expect(captured!.get('X-Custom')).toBe('hi');
    expect(captured!.get('Accept')).toBe('application/json');
  });

  it('exposes the spec Error body via the openapi-fetch error channel on 4xx', async () => {
    server.use(
      http.get('http://app.test/api/admin/modules', () =>
        HttpResponse.json(
          { message: 'Invalid input', error_type: 'invalid_input', errors: { limit: ['too big'] } },
          { status: 422 },
        ),
      ),
    );

    const api = createRbacApi({ baseUrl: 'http://app.test/api/admin' });
    const { data, error } = await api.GET('/modules');

    expect(data).toBeUndefined();
    expect(error).toMatchObject({ message: 'Invalid input', error_type: 'invalid_input' });
  });
});
