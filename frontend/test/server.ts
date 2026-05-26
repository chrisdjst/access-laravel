import { http, HttpResponse } from 'msw';
import { setupServer } from 'msw/node';

import { fixtures } from './fixtures.js';

const API = 'http://app.test/api/admin';

/**
 * Default handlers for the happy path. Tests that need to assert
 * specific request shapes or simulate errors call `server.use(...)`
 * with overrides, which `afterEach` cleans up.
 */
export const handlers = [
  // Modules
  http.get(`${API}/modules`, () =>
    HttpResponse.json({ data: fixtures.modules, meta: { count: fixtures.modules.length } }),
  ),
  http.post(`${API}/modules`, async ({ request }) => {
    const body = (await request.json()) as Record<string, unknown>;

    return HttpResponse.json(
      { data: { ...fixtures.modules[0], ...body, id: 'new-module-uuid' } },
      { status: 201 },
    );
  }),
  http.delete(`${API}/modules/bulk`, () => new HttpResponse(null, { status: 204 })),
  http.put(`${API}/modules/:id`, async ({ params, request }) => {
    const body = (await request.json()) as Record<string, unknown>;

    return HttpResponse.json({
      data: { ...fixtures.modules[0], id: params.id as string, ...body },
    });
  }),
  http.delete(`${API}/modules/:id`, () => new HttpResponse(null, { status: 204 })),

  // Roles
  http.get(`${API}/roles`, () =>
    HttpResponse.json({ data: fixtures.roles, meta: { count: fixtures.roles.length } }),
  ),
  http.get(`${API}/roles/:role`, ({ params }) =>
    HttpResponse.json({
      data: { ...fixtures.roles[0], id: params.role as string },
    }),
  ),
  http.put(`${API}/roles/:role`, async ({ params, request }) => {
    const body = (await request.json()) as Record<string, unknown>;

    return HttpResponse.json({
      data: { ...fixtures.roles[0], id: params.role as string, ...body },
    });
  }),
  http.put(`${API}/roles/:role/modules`, async ({ params }) =>
    HttpResponse.json({
      data: { ...fixtures.roles[0], id: params.role as string },
    }),
  ),

  // Languages
  http.get(`${API}/languages`, () => HttpResponse.json({ data: fixtures.languages })),
  http.post(`${API}/languages`, async ({ request }) => {
    const body = (await request.json()) as Record<string, unknown>;

    return HttpResponse.json(
      { data: { ...fixtures.languages[0], ...body, id: 'new-lang-uuid' } },
      { status: 201 },
    );
  }),
  http.put(`${API}/languages/:id`, async ({ params, request }) => {
    const body = (await request.json()) as Record<string, unknown>;

    return HttpResponse.json({
      data: { ...fixtures.languages[0], id: params.id as string, ...body },
    });
  }),
  http.delete(`${API}/languages/:id`, () => new HttpResponse(null, { status: 204 })),
  http.put(`${API}/languages/:id/default`, ({ params }) =>
    HttpResponse.json({
      data: { ...fixtures.languages[0], id: params.id as string, is_default: true },
    }),
  ),
];

export const server = setupServer(...handlers);
