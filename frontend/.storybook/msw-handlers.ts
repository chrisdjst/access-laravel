import { http, HttpResponse } from 'msw';

/**
 * Default msw handlers backing every Storybook story.
 *
 * Stories that need empty / error / loading states override these
 * via `parameters.msw.handlers = [...]` — the addon will use the
 * overrides in addition to (and ahead of) what's here, so the most
 * specific match wins per request.
 *
 * Keep the data shape aligned with what F7-F10 components consume.
 */

const API = 'http://app.test/api/admin';

const modules = [
  {
    id: '11111111-1111-1111-1111-111111111111',
    slug: 'events',
    name: 'Events',
    icon: 'calendar',
    redirect: '/admin/events',
    root_module_id: null,
    sort_order: 10,
    is_active: true,
    translations: {},
    created_at: '2026-05-25T12:00:00+00:00',
    updated_at: '2026-05-25T12:00:00+00:00',
  },
  {
    id: '11111111-1111-1111-1111-222222222222',
    slug: 'billing',
    name: 'Billing',
    icon: 'receipt',
    redirect: '/admin/billing',
    root_module_id: null,
    sort_order: 20,
    is_active: true,
    translations: {},
    created_at: '2026-05-25T12:00:00+00:00',
    updated_at: '2026-05-25T12:00:00+00:00',
  },
];

const roles = [
  {
    id: '22222222-2222-2222-2222-aaaaaaaaaaaa',
    name: 'admin',
    display_name: 'Administrator',
    guard_name: 'admin',
    organization_id: null,
    level: 100,
    is_system: false,
    parent_role_id: null,
    translations: {},
    modules: [],
    created_at: '2026-05-25T12:00:00+00:00',
    updated_at: '2026-05-25T12:00:00+00:00',
  },
  {
    id: '22222222-2222-2222-2222-bbbbbbbbbbbb',
    name: 'viewer',
    display_name: 'Viewer',
    guard_name: 'admin',
    organization_id: null,
    level: 10,
    is_system: false,
    parent_role_id: null,
    translations: {},
    modules: [],
    created_at: '2026-05-25T12:00:00+00:00',
    updated_at: '2026-05-25T12:00:00+00:00',
  },
];

const languages = [
  { id: '33333333-3333-3333-3333-aaaa', code: 'en', name: 'English', is_default: true },
  { id: '33333333-3333-3333-3333-bbbb', code: 'pt_BR', name: 'Português', is_default: false },
];

export const defaultHandlers = [
  http.get(`${API}/modules`, () =>
    HttpResponse.json({ data: modules, meta: { count: modules.length } }),
  ),
  http.get(`${API}/roles`, () =>
    HttpResponse.json({ data: roles, meta: { count: roles.length } }),
  ),
  http.get(`${API}/languages`, () => HttpResponse.json({ data: languages })),
];
