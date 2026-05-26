import { waitFor } from '@testing-library/react';
import { http, HttpResponse } from 'msw';
import { describe, expect, it } from 'vitest';

import { fixtures } from '../../test/fixtures.js';
import { server } from '../../test/server.js';
import { renderHookWithProviders } from '../../test/wrap.js';
import {
  useAdminLanguages,
  useAdminModules,
  useAdminRole,
  useAdminRoles,
  useBulkDeleteModules,
  useCloneRole,
  useCreateLanguage,
  useCreateModule,
  useCreateRole,
  useDeleteLanguage,
  useDeleteModule,
  useDeleteRole,
  useRestoreRole,
  useSetDefaultLanguage,
  useSyncRoleModules,
  useUpdateLanguage,
  useUpdateModule,
  useUpdateRole,
} from './useRbac.js';

const API = 'http://app.test/api/admin';

// ============================================================================
// Modules
// ============================================================================

describe('useAdminModules', () => {
  it('returns the module list from GET /modules', async () => {
    const { result } = renderHookWithProviders(() => useAdminModules());

    await waitFor(() => expect(result.current.isSuccess).toBe(true));
    // Response shape: { data: Module[], meta: { count } }
    expect(result.current.data?.data?.[0]?.slug).toBe('events');
  });
});

describe('useUpdateModule', () => {
  it('PUTs the payload and surfaces the updated module', async () => {
    const { result } = renderHookWithProviders(() => useUpdateModule());

    result.current.mutate({
      id: fixtures.modules[0]!.id,
      payload: { name: 'Renamed Events' },
    });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));
    expect(result.current.data?.data?.name).toBe('Renamed Events');
  });

  it('fires the onErrorMessage callback on a 422 response', async () => {
    server.use(
      http.put(`${API}/modules/:id`, () =>
        HttpResponse.json({ message: 'Validation failed' }, { status: 422 }),
      ),
    );

    let errorMessage: string | undefined;
    const { result } = renderHookWithProviders(() =>
      useUpdateModule({ onErrorMessage: (m) => (errorMessage = m) }),
    );

    result.current.mutate({ id: fixtures.modules[0]!.id, payload: { name: 'x' } });

    await waitFor(() => expect(result.current.isError).toBe(true));
    expect(errorMessage).toBe('Erro ao atualizar módulo.');
  });
});

describe('useCreateModule', () => {
  it('POSTs and resolves with the new module', async () => {
    const { result } = renderHookWithProviders(() => useCreateModule());

    result.current.mutate({
      slug: 'reports',
      name: 'Reports',
      icon: null,
      root_module_id: null,
      sort_order: 0,
      is_active: true,
      translations: {},
    });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));
    expect(result.current.data?.data?.slug).toBe('reports');
    expect(result.current.data?.data?.id).toBe('new-module-uuid');
  });
});

describe('useDeleteModule', () => {
  it('DELETEs and resolves on 204', async () => {
    const { result } = renderHookWithProviders(() => useDeleteModule());

    result.current.mutate(fixtures.modules[0]!.id);

    await waitFor(() => expect(result.current.isSuccess).toBe(true));
  });
});

describe('useBulkDeleteModules', () => {
  it('DELETEs /modules/bulk with an ids array and resolves on 204', async () => {
    let capturedBody: { ids?: string[] } | null = null;
    server.use(
      http.delete(`${API}/modules/bulk`, async ({ request }) => {
        capturedBody = (await request.json()) as { ids?: string[] };

        return new HttpResponse(null, { status: 204 });
      }),
    );

    const ids = [fixtures.modules[0]!.id, fixtures.modules[1]?.id ?? 'second-id'];
    const { result } = renderHookWithProviders(() => useBulkDeleteModules());

    result.current.mutate({ ids });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));
    expect(capturedBody?.ids).toEqual(ids);
  });
});

// ============================================================================
// Roles
// ============================================================================

describe('useAdminRoles', () => {
  it('returns the role list', async () => {
    const { result } = renderHookWithProviders(() => useAdminRoles());

    await waitFor(() => expect(result.current.isSuccess).toBe(true));
    expect(result.current.data?.data?.[0]?.name).toBe('admin');
  });

  it('forwards query params to the request URL', async () => {
    let capturedUrl: string | null = null;
    server.use(
      http.get(`${API}/roles`, ({ request }) => {
        capturedUrl = request.url;

        return HttpResponse.json({ data: [], meta: { total: 0, limit: 10, offset: 0 } });
      }),
    );

    const { result } = renderHookWithProviders(() => useAdminRoles({ guard: 'admin', limit: 10 }));

    await waitFor(() => expect(result.current.isSuccess).toBe(true));
    expect(capturedUrl).toContain('guard=admin');
    expect(capturedUrl).toContain('limit=10');
  });
});

describe('useAdminRole', () => {
  it('skips the request when id is null', () => {
    const { result } = renderHookWithProviders(() => useAdminRole(null));

    expect(result.current.fetchStatus).toBe('idle');
  });

  it('fetches a single role when id is provided', async () => {
    const { result } = renderHookWithProviders(() => useAdminRole(fixtures.roles[0]!.id));

    await waitFor(() => expect(result.current.isSuccess).toBe(true));
    expect(result.current.data?.data?.id).toBe(fixtures.roles[0]!.id);
  });
});

describe('useUpdateRole', () => {
  it('updates role display_name + translations via PUT /roles/{role}', async () => {
    const { result } = renderHookWithProviders(() => useUpdateRole());

    result.current.mutate({
      id: fixtures.roles[0]!.id,
      payload: { display_name: 'Admin (BR)', translations: {} },
    });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));
    expect(result.current.data?.data?.display_name).toBe('Admin (BR)');
  });
});

describe('useSyncRoleModules', () => {
  it('PUTs the module matrix and resolves with the updated role', async () => {
    const { result } = renderHookWithProviders(() => useSyncRoleModules());

    result.current.mutate({
      roleId: fixtures.roles[0]!.id,
      payload: {
        modules: [{ module_id: fixtures.modules[0]!.id, is_reading_allowed: true }],
      },
    });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));
    expect(result.current.data?.data?.id).toBe(fixtures.roles[0]!.id);
  });
});

describe('useCreateRole', () => {
  it('POSTs and resolves with the new role', async () => {
    const { result } = renderHookWithProviders(() => useCreateRole());

    result.current.mutate({
      name: 'reviewer',
      display_name: 'Reviewer',
      guard_name: 'admin',
      level: 40,
    });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));
    expect(result.current.data?.data?.name).toBe('reviewer');
    expect(result.current.data?.data?.id).toBe('new-role-uuid');
  });
});

describe('useCloneRole', () => {
  it('POSTs to /roles/:id/clone and resolves with the cloned role', async () => {
    const { result } = renderHookWithProviders(() => useCloneRole());

    result.current.mutate({
      sourceRoleId: fixtures.roles[0]!.id,
      payload: { name: 'admin_copy', display_name: 'Admin Copy' },
    });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));
    expect(result.current.data?.data?.id).toBe('cloned-role-uuid');
    expect(result.current.data?.data?.name).toBe('admin_copy');
  });
});

describe('useDeleteRole', () => {
  it('DELETEs and resolves on 204', async () => {
    const { result } = renderHookWithProviders(() => useDeleteRole());

    result.current.mutate(fixtures.roles[0]!.id);

    await waitFor(() => expect(result.current.isSuccess).toBe(true));
  });
});

describe('useRestoreRole', () => {
  it('POSTs to /roles/:id/restore and resolves with the restored role', async () => {
    const { result } = renderHookWithProviders(() => useRestoreRole());

    result.current.mutate(fixtures.roles[0]!.id);

    await waitFor(() => expect(result.current.isSuccess).toBe(true));
    expect(result.current.data?.data?.deleted_at).toBeNull();
  });
});

// ============================================================================
// Languages
// ============================================================================

describe('useAdminLanguages', () => {
  it('returns the language list', async () => {
    const { result } = renderHookWithProviders(() => useAdminLanguages());

    await waitFor(() => expect(result.current.isSuccess).toBe(true));
    expect(result.current.data?.data?.[0]?.code).toBe('en');
  });
});

describe('useCreateLanguage', () => {
  it('POSTs and resolves with the new language', async () => {
    const { result } = renderHookWithProviders(() => useCreateLanguage());

    result.current.mutate({ code: 'pt_BR', name: 'Português', is_default: false });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));
    expect(result.current.data?.data?.code).toBe('pt_BR');
  });
});

describe('useUpdateLanguage', () => {
  it('partial update via PUT /languages/{id}', async () => {
    const { result } = renderHookWithProviders(() => useUpdateLanguage());

    result.current.mutate({
      id: fixtures.languages[0]!.id,
      payload: { name: 'English (UK)' },
    });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));
    expect(result.current.data?.data?.name).toBe('English (UK)');
  });
});

describe('useDeleteLanguage', () => {
  it('DELETEs and resolves on 204', async () => {
    const { result } = renderHookWithProviders(() => useDeleteLanguage());

    result.current.mutate(fixtures.languages[0]!.id);

    await waitFor(() => expect(result.current.isSuccess).toBe(true));
  });
});

describe('useSetDefaultLanguage', () => {
  it('flips the default flag via PUT /languages/{id}/default', async () => {
    const { result } = renderHookWithProviders(() => useSetDefaultLanguage());

    result.current.mutate(fixtures.languages[0]!.id);

    await waitFor(() => expect(result.current.isSuccess).toBe(true));
    expect(result.current.data?.data?.is_default).toBe(true);
  });
});
