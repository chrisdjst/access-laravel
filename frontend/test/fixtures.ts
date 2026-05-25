/**
 * Hard-coded fixture data backing the msw handlers. Keep tiny and
 * representative — tests that need extra fields override per-request
 * via server.use().
 */

export const fixtures = {
  modules: [
    {
      id: '11111111-1111-1111-1111-111111111111',
      slug: 'events',
      name: 'Events',
      redirect: '/admin/events',
      icon: 'calendar',
      root_module_id: null,
      sort_order: 10,
      is_active: true,
      translations: {},
      created_at: '2026-05-25T12:00:00+00:00',
      updated_at: '2026-05-25T12:00:00+00:00',
    },
  ],
  roles: [
    {
      id: '22222222-2222-2222-2222-222222222222',
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
  ],
  languages: [
    {
      id: '33333333-3333-3333-3333-333333333333',
      code: 'en',
      name: 'English',
      is_default: true,
    },
  ],
} as const;
