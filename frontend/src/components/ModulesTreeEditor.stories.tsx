import type { Meta, StoryObj } from '@storybook/react';
import { http, HttpResponse } from 'msw';

import { ModulesTreeEditor } from './ModulesTreeEditor.js';

const API = 'http://app.test/api/admin';

const meta: Meta<typeof ModulesTreeEditor> = {
  title: 'Components/ModulesTreeEditor',
  component: ModulesTreeEditor,
  parameters: { layout: 'fullscreen' },
};

export default meta;
type Story = StoryObj<typeof ModulesTreeEditor>;

const makeModule = (overrides: Record<string, unknown> = {}) => ({
  id: crypto.randomUUID(),
  slug: 'events',
  name: 'Events',
  redirect: null,
  icon: null,
  root_module_id: null,
  sort_order: 0,
  is_active: true,
  translations: {},
  created_at: '2026-05-25T12:00:00+00:00',
  updated_at: '2026-05-25T12:00:00+00:00',
  ...overrides,
});

export const FlatFiveModules: Story = {
  parameters: {
    msw: {
      handlers: [
        http.get(`${API}/modules`, () =>
          HttpResponse.json({
            data: [
              makeModule({ id: 'events', slug: 'events', name: 'Events', sort_order: 0 }),
              makeModule({ id: 'reports', slug: 'reports', name: 'Reports', sort_order: 1 }),
              makeModule({ id: 'settings', slug: 'settings', name: 'Settings', sort_order: 2 }),
              makeModule({ id: 'users', slug: 'users', name: 'Users', sort_order: 3 }),
              makeModule({
                id: 'audit',
                slug: 'audit',
                name: 'Audit',
                sort_order: 4,
                is_active: false,
              }),
            ],
            meta: { count: 5 },
          }),
        ),
      ],
    },
  },
};

export const NestedTree: Story = {
  parameters: {
    msw: {
      handlers: [
        http.get(`${API}/modules`, () =>
          HttpResponse.json({
            data: [
              makeModule({ id: 'admin', slug: 'admin', name: 'Admin', sort_order: 0 }),
              makeModule({
                id: 'admin.users',
                slug: 'admin.users',
                name: 'Users',
                root_module_id: 'admin',
                sort_order: 0,
              }),
              makeModule({
                id: 'admin.roles',
                slug: 'admin.roles',
                name: 'Roles',
                root_module_id: 'admin',
                sort_order: 1,
              }),
              makeModule({
                id: 'admin.audit',
                slug: 'admin.audit',
                name: 'Audit',
                root_module_id: 'admin',
                sort_order: 2,
              }),
              makeModule({ id: 'events', slug: 'events', name: 'Events', sort_order: 1 }),
              makeModule({
                id: 'events.upcoming',
                slug: 'events.upcoming',
                name: 'Upcoming',
                root_module_id: 'events',
                sort_order: 0,
              }),
              makeModule({
                id: 'events.archive',
                slug: 'events.archive',
                name: 'Archive',
                root_module_id: 'events',
                sort_order: 1,
              }),
              makeModule({ id: 'settings', slug: 'settings', name: 'Settings', sort_order: 2 }),
              makeModule({
                id: 'settings.languages',
                slug: 'settings.languages',
                name: 'Languages',
                root_module_id: 'settings',
                sort_order: 0,
              }),
              makeModule({
                id: 'settings.profile',
                slug: 'settings.profile',
                name: 'Profile',
                root_module_id: 'settings',
                sort_order: 1,
              }),
              makeModule({
                id: 'settings.security',
                slug: 'settings.security',
                name: 'Security',
                root_module_id: 'settings',
                sort_order: 2,
              }),
            ],
            meta: { count: 11 },
          }),
        ),
      ],
    },
  },
};

export const HundredModulesPerf: Story = {
  parameters: {
    msw: {
      handlers: [
        http.get(`${API}/modules`, () => {
          const data = Array.from({ length: 100 }, (_, i) =>
            makeModule({
              id: `m-${i}`,
              slug: `module_${i.toString().padStart(3, '0')}`,
              name: `Module ${i + 1}`,
              sort_order: i,
            }),
          );

          return HttpResponse.json({ data, meta: { count: data.length } });
        }),
      ],
    },
  },
};

export const Empty: Story = {
  parameters: {
    msw: {
      handlers: [
        http.get(`${API}/modules`, () =>
          HttpResponse.json({ data: [], meta: { count: 0 } }),
        ),
      ],
    },
  },
};
