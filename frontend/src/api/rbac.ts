import type {
  AdminLanguage,
  AdminModule,
  AdminRole,
  LanguagePayload,
  SyncRoleModulesPayload,
  UpdateModulePayload,
  UpdateRolePayload,
} from '../types/index.js';

/**
 * Minimal HTTP client surface the package needs. Compatible with axios,
 * ky-with-adapter, or any wrapper that returns `{ data: T }` responses.
 * Keeping this interface lean means the package has zero axios dependency
 * in its type surface — hosts plug in whichever HTTP lib they already use.
 */
export interface HttpClient {
  get: <T>(url: string, config?: { params?: Record<string, unknown> }) => Promise<{ data: T }>;
  post: <T>(url: string, body?: unknown) => Promise<{ data: T }>;
  put: <T>(url: string, body?: unknown) => Promise<{ data: T }>;
  delete: (url: string) => Promise<unknown>;
}

/**
 * Build the RBAC HTTP client bound to a host-provided HTTP client. The
 * instance carries the host app's base URL, auth interceptors, and locale
 * headers — the package never sees axios directly so consumers can plug in
 * whatever auth + base URL setup they already have.
 */
export function createRbacApi(client: HttpClient) {
  return {
    listModules: () => client.get<{ data: AdminModule[] }>('/admin/modules'),
    updateModule: (id: string, payload: UpdateModulePayload) =>
      client.put<{ data: AdminModule }>(`/admin/modules/${id}`, payload),

    listRoles: (params?: { guard?: string }) =>
      client.get<{ data: AdminRole[] }>('/admin/roles', { params }),
    getRole: (id: string) =>
      client.get<{ data: AdminRole }>(`/admin/roles/${id}`),
    updateRole: (id: string, payload: UpdateRolePayload) =>
      client.put<{ data: AdminRole }>(`/admin/roles/${id}`, payload),
    syncRoleModules: (roleId: string, payload: SyncRoleModulesPayload) =>
      client.put<{ data: AdminRole }>(`/admin/roles/${roleId}/modules`, payload),

    listLanguages: () => client.get<{ data: AdminLanguage[] }>('/admin/languages'),
    createLanguage: (payload: LanguagePayload) =>
      client.post<{ data: AdminLanguage }>('/admin/languages', payload),
    updateLanguage: (id: string, payload: Partial<LanguagePayload>) =>
      client.put<{ data: AdminLanguage }>(`/admin/languages/${id}`, payload),
    deleteLanguage: (id: string) =>
      client.delete(`/admin/languages/${id}`),
    setDefaultLanguage: (id: string) =>
      client.put<{ data: AdminLanguage }>(`/admin/languages/${id}/default`),
  };
}

export type RbacApi = ReturnType<typeof createRbacApi>;
