import type { Meta, StoryObj } from '@storybook/react';
import { http, HttpResponse } from 'msw';

import { RolesPage } from './RolesPage.js';

const API = 'http://app.test/api/admin';

const meta: Meta<typeof RolesPage> = {
  title: 'Components/RolesPage',
  component: RolesPage,
  parameters: {
    layout: 'fullscreen',
  },
};

export default meta;
type Story = StoryObj<typeof RolesPage>;

const makeRole = (overrides: Record<string, unknown> = {}) => ({
  id: crypto.randomUUID(),
  name: 'editor',
  display_name: 'Editor',
  guard_name: 'admin',
  organization_id: null,
  level: 50,
  is_system: false,
  parent_role_id: null,
  translations: {},
  modules: [],
  created_at: '2026-05-25T12:00:00+00:00',
  updated_at: '2026-05-25T12:00:00+00:00',
  ...overrides,
});

export const Empty: Story = {
  parameters: {
    msw: {
      handlers: [
        http.get(`${API}/roles`, () =>
          HttpResponse.json({ data: [], meta: { total: 0, limit: 25, offset: 0 } }),
        ),
      ],
    },
  },
};

export const Paginated: Story = {
  parameters: {
    msw: {
      handlers: [
        http.get(`${API}/roles`, ({ request }) => {
          const url = new URL(request.url);
          const offset = parseInt(url.searchParams.get('offset') ?? '0', 10);
          const limit = parseInt(url.searchParams.get('limit') ?? '25', 10);
          const allRoles = Array.from({ length: 67 }, (_, i) =>
            makeRole({
              id: `role-${i}`,
              name: `role_${i.toString().padStart(2, '0')}`,
              display_name: `Role ${i + 1}`,
              level: Math.max(0, 100 - i),
            }),
          );
          const slice = allRoles.slice(offset, offset + limit);

          return HttpResponse.json({
            data: slice,
            meta: { total: allRoles.length, limit, offset },
          });
        }),
      ],
    },
  },
};

export const SystemRole: Story = {
  parameters: {
    msw: {
      handlers: [
        http.get(`${API}/roles`, () =>
          HttpResponse.json({
            data: [
              makeRole({ name: 'super_admin', display_name: 'Super Admin', is_system: true, level: 100 }),
              makeRole({ name: 'editor', display_name: 'Editor', level: 50 }),
            ],
            meta: { total: 2, limit: 25, offset: 0 },
          }),
        ),
      ],
    },
  },
};

export const WithTrashed: Story = {
  parameters: {
    msw: {
      handlers: [
        http.get(`${API}/roles`, () =>
          HttpResponse.json({
            data: [
              makeRole({ name: 'editor', display_name: 'Editor' }),
              makeRole({
                name: 'old_role',
                display_name: 'Old Role',
                deleted_at: '2026-05-20T10:00:00+00:00',
              }),
            ],
            meta: { total: 2, limit: 25, offset: 0 },
          }),
        ),
      ],
    },
  },
  render: (args) => <RolesPage {...args} />,
};

export const Localized: Story = {
  args: {
    labels: {
      title: 'Perfis',
      createButton: 'Novo perfil',
      cloneButton: 'Clonar',
      deleteButton: 'Excluir',
      restoreButton: 'Restaurar',
      createModalTitle: 'Novo perfil',
      cloneModalTitle: 'Clonar perfil',
      deleteConfirmTitle: 'Excluir perfil?',
      deleteConfirmDescription:
        'O perfil é soft-deleted. Usuários atribuídos perdem acesso até ser restaurado.',
      systemBadge: 'sistema',
      trashedBadge: 'na lixeira',
      empty: 'Nenhum perfil ainda. Clique em "Novo perfil" para começar.',
      showTrashed: 'Mostrar removidos',
      hideTrashed: 'Ocultar removidos',
    },
  },
  parameters: {
    msw: {
      handlers: [
        http.get(`${API}/roles`, () =>
          HttpResponse.json({
            data: [makeRole({ name: 'admin', display_name: 'Administrador', is_system: true })],
            meta: { total: 1, limit: 25, offset: 0 },
          }),
        ),
      ],
    },
  },
};
