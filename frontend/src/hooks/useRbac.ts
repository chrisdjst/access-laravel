import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import type { QueryClient } from '@tanstack/react-query';

import type { components, paths } from '../api/rbac.js';
import { useRbacApi } from '../provider.js';

/**
 * Optional callbacks for mutation feedback. The package stays toast-agnostic
 * (no sonner / hot-toast import) so hosts wire whichever lib they prefer.
 */
export interface MutationCallbacks {
  onSuccessMessage?: (msg: string) => void;
  onErrorMessage?: (msg: string) => void;
}

// Convenience aliases derived from the spec.
type Module = components['schemas']['Module'];
type Role = components['schemas']['Role'];
type Language = components['schemas']['Language'];
type AuditEntry = components['schemas']['AuditEntry'];

type UpdateModulePayload = NonNullable<paths['/modules/{id}']['put']['requestBody']> extends {
  content: { 'application/json': infer T };
}
  ? T
  : never;

type CreateModulePayload = NonNullable<paths['/modules']['post']['requestBody']> extends {
  content: { 'application/json': infer T };
}
  ? T
  : never;

type BulkDeleteModulesPayload = NonNullable<
  paths['/modules/bulk']['delete']['requestBody']
> extends {
  content: { 'application/json': infer T };
}
  ? T
  : never;

type UpdateRolePayload = NonNullable<paths['/roles/{role}']['put']['requestBody']> extends {
  content: { 'application/json': infer T };
}
  ? T
  : never;

type CreateRolePayload = NonNullable<paths['/roles']['post']['requestBody']> extends {
  content: { 'application/json': infer T };
}
  ? T
  : never;

type CloneRolePayload = NonNullable<paths['/roles/{role}/clone']['post']['requestBody']> extends {
  content: { 'application/json': infer T };
}
  ? T
  : never;

type SyncRoleModulesPayload = NonNullable<
  paths['/roles/{role}/modules']['put']['requestBody']
> extends {
  content: { 'application/json': infer T };
}
  ? T
  : never;

type LanguagePayload = NonNullable<paths['/languages']['post']['requestBody']> extends {
  content: { 'application/json': infer T };
}
  ? T
  : never;

/**
 * Unwraps the `{ data, error }` shape openapi-fetch returns. Throws on
 * error so React Query's onError fires. We re-throw the decoded body so
 * hosts can pattern-match on the spec's Error schema.
 *
 * 204 No Content responses (e.g. DELETE) resolve with `data === undefined`
 * + `error === undefined`. We return `null` in that case — mutations
 * don't care about the body shape, and queries that targeted a 204 are
 * broken anyway.
 */
async function unwrap<T>(promise: Promise<{ data?: T; error?: unknown }>): Promise<T | null> {
  const { data, error } = await promise;
  if (error !== undefined) {
    throw error;
  }

  return (data ?? null) as T | null;
}

// ============================================================================
// Modules
// ============================================================================

export function useAdminModules() {
  const api = useRbacApi();

  return useQuery({
    queryKey: ['admin', 'modules'],
    queryFn: () => unwrap(api.GET('/modules')),
  });
}

export function useUpdateModule(cb?: MutationCallbacks) {
  const api = useRbacApi();
  const qc = useQueryClient();

  return useMutation({
    mutationFn: ({ id, payload }: { id: string; payload: UpdateModulePayload }) =>
      unwrap(
        api.PUT('/modules/{id}', {
          params: { path: { id } },
          body: payload,
        }),
      ),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['admin', 'modules'] });
      qc.invalidateQueries({ queryKey: ['me', 'modules'] });
      cb?.onSuccessMessage?.('Módulo atualizado!');
    },
    onError: () => cb?.onErrorMessage?.('Erro ao atualizar módulo.'),
  });
}

export function useCreateModule(cb?: MutationCallbacks) {
  const api = useRbacApi();
  const qc = useQueryClient();

  return useMutation({
    mutationFn: (payload: CreateModulePayload) =>
      unwrap(api.POST('/modules', { body: payload })),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['admin', 'modules'] });
      qc.invalidateQueries({ queryKey: ['me', 'modules'] });
      cb?.onSuccessMessage?.('Módulo criado!');
    },
    onError: () => cb?.onErrorMessage?.('Erro ao criar módulo.'),
  });
}

export function useDeleteModule(cb?: MutationCallbacks) {
  const api = useRbacApi();
  const qc = useQueryClient();

  return useMutation({
    mutationFn: (id: string) =>
      unwrap(api.DELETE('/modules/{id}', { params: { path: { id } } })),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['admin', 'modules'] });
      qc.invalidateQueries({ queryKey: ['me', 'modules'] });
      cb?.onSuccessMessage?.('Módulo removido!');
    },
    onError: () => cb?.onErrorMessage?.('Não foi possível remover o módulo.'),
  });
}

export function useBulkDeleteModules(cb?: MutationCallbacks) {
  const api = useRbacApi();
  const qc = useQueryClient();

  return useMutation({
    mutationFn: (payload: BulkDeleteModulesPayload) =>
      unwrap(api.DELETE('/modules/bulk', { body: payload })),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['admin', 'modules'] });
      qc.invalidateQueries({ queryKey: ['me', 'modules'] });
      cb?.onSuccessMessage?.('Módulos removidos!');
    },
    onError: () => cb?.onErrorMessage?.('Falha ao remover módulos em lote.'),
  });
}

export type { CreateModulePayload, BulkDeleteModulesPayload };

// ============================================================================
// Roles
// ============================================================================

type RolesIndexQuery = paths['/roles']['get']['parameters']['query'];

export function useAdminRoles(query?: RolesIndexQuery) {
  const api = useRbacApi();

  return useQuery({
    queryKey: ['admin', 'roles', query ?? {}],
    queryFn: () => unwrap(api.GET('/roles', { params: { query } })),
  });
}

export function useAdminRole(id: string | null) {
  const api = useRbacApi();

  return useQuery({
    queryKey: ['admin', 'roles', id],
    queryFn: () => unwrap(api.GET('/roles/{role}', { params: { path: { role: id! } } })),
    enabled: !!id,
  });
}

export function useUpdateRole(cb?: MutationCallbacks) {
  const api = useRbacApi();
  const qc = useQueryClient();

  return useMutation({
    mutationFn: ({ id, payload }: { id: string; payload: UpdateRolePayload }) =>
      unwrap(api.PUT('/roles/{role}', { params: { path: { role: id } }, body: payload })),
    onSuccess: (_, { id }) => {
      qc.invalidateQueries({ queryKey: ['admin', 'roles'] });
      qc.invalidateQueries({ queryKey: ['admin', 'roles', id] });
      cb?.onSuccessMessage?.('Perfil atualizado!');
    },
    onError: () => cb?.onErrorMessage?.('Erro ao atualizar perfil.'),
  });
}

export function useCreateRole(cb?: MutationCallbacks) {
  const api = useRbacApi();
  const qc = useQueryClient();

  return useMutation({
    mutationFn: (payload: CreateRolePayload) => unwrap(api.POST('/roles', { body: payload })),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['admin', 'roles'] });
      cb?.onSuccessMessage?.('Perfil criado!');
    },
    onError: () => cb?.onErrorMessage?.('Erro ao criar perfil.'),
  });
}

export function useCloneRole(cb?: MutationCallbacks) {
  const api = useRbacApi();
  const qc = useQueryClient();

  return useMutation({
    mutationFn: ({ sourceId, payload }: { sourceId: string; payload: CloneRolePayload }) =>
      unwrap(
        api.POST('/roles/{role}/clone', {
          params: { path: { role: sourceId } },
          body: payload,
        }),
      ),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['admin', 'roles'] });
      cb?.onSuccessMessage?.('Perfil clonado!');
    },
    onError: () => cb?.onErrorMessage?.('Erro ao clonar perfil.'),
  });
}

export function useDeleteRole(cb?: MutationCallbacks) {
  const api = useRbacApi();
  const qc = useQueryClient();

  return useMutation({
    mutationFn: (id: string) =>
      unwrap(api.DELETE('/roles/{role}', { params: { path: { role: id } } })),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['admin', 'roles'] });
      cb?.onSuccessMessage?.('Perfil removido!');
    },
    onError: () => cb?.onErrorMessage?.('Erro ao remover perfil.'),
  });
}

export function useRestoreRole(cb?: MutationCallbacks) {
  const api = useRbacApi();
  const qc = useQueryClient();

  return useMutation({
    mutationFn: (id: string) =>
      unwrap(
        api.POST('/roles/{role}/restore', {
          params: { path: { role: id } },
          body: undefined as never,
        }),
      ),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['admin', 'roles'] });
      cb?.onSuccessMessage?.('Perfil restaurado!');
    },
    onError: () => cb?.onErrorMessage?.('Erro ao restaurar perfil.'),
  });
}

export function useSyncRoleModules(cb?: MutationCallbacks) {
  const api = useRbacApi();
  const qc = useQueryClient();

  return useMutation({
    mutationFn: ({ roleId, payload }: { roleId: string; payload: SyncRoleModulesPayload }) =>
      unwrap(
        api.PUT('/roles/{role}/modules', {
          params: { path: { role: roleId } },
          body: payload,
        }),
      ),
    onSuccess: (_, { roleId }) => {
      qc.invalidateQueries({ queryKey: ['admin', 'roles'] });
      qc.invalidateQueries({ queryKey: ['admin', 'roles', roleId] });
      qc.invalidateQueries({ queryKey: ['me', 'modules'] });
      cb?.onSuccessMessage?.('Permissões atualizadas!');
    },
    onError: () => cb?.onErrorMessage?.('Erro ao salvar permissões.'),
  });
}

// ============================================================================
// Languages
// ============================================================================

function invalidateLanguages(qc: QueryClient) {
  qc.invalidateQueries({ queryKey: ['admin', 'languages'] });
  qc.invalidateQueries({ queryKey: ['admin', 'modules'] });
  qc.invalidateQueries({ queryKey: ['me', 'modules'] });
}

export function useAdminLanguages() {
  const api = useRbacApi();

  return useQuery({
    queryKey: ['admin', 'languages'],
    queryFn: () => unwrap(api.GET('/languages')),
  });
}

export function useCreateLanguage(cb?: MutationCallbacks) {
  const api = useRbacApi();
  const qc = useQueryClient();

  return useMutation({
    mutationFn: (payload: LanguagePayload) => unwrap(api.POST('/languages', { body: payload })),
    onSuccess: () => {
      invalidateLanguages(qc);
      cb?.onSuccessMessage?.('Idioma criado!');
    },
    onError: () => cb?.onErrorMessage?.('Erro ao criar idioma.'),
  });
}

export function useUpdateLanguage(cb?: MutationCallbacks) {
  const api = useRbacApi();
  const qc = useQueryClient();

  return useMutation({
    mutationFn: ({ id, payload }: { id: string; payload: Partial<LanguagePayload> }) =>
      unwrap(
        api.PUT('/languages/{id}', {
          params: { path: { id } },
          body: payload as LanguagePayload,
        }),
      ),
    onSuccess: () => {
      invalidateLanguages(qc);
      cb?.onSuccessMessage?.('Idioma atualizado!');
    },
    onError: () => cb?.onErrorMessage?.('Erro ao atualizar idioma.'),
  });
}

export function useDeleteLanguage(cb?: MutationCallbacks) {
  const api = useRbacApi();
  const qc = useQueryClient();

  return useMutation({
    mutationFn: (id: string) =>
      unwrap(api.DELETE('/languages/{id}', { params: { path: { id } } })),
    onSuccess: () => {
      invalidateLanguages(qc);
      cb?.onSuccessMessage?.('Idioma removido!');
    },
    onError: () => cb?.onErrorMessage?.('Não foi possível remover o idioma.'),
  });
}

export function useSetDefaultLanguage(cb?: MutationCallbacks) {
  const api = useRbacApi();
  const qc = useQueryClient();

  return useMutation({
    mutationFn: (id: string) =>
      unwrap(
        api.PUT('/languages/{id}/default', {
          params: { path: { id } },
          // The endpoint has no request body; cast `body` away so
          // openapi-fetch doesn't complain — sending an empty body
          // matches Laravel's PUT-without-body convention.
          body: undefined as never,
        }),
      ),
    onSuccess: () => {
      invalidateLanguages(qc);
      cb?.onSuccessMessage?.('Idioma padrão atualizado!');
    },
    onError: () => cb?.onErrorMessage?.('Erro ao definir idioma padrão.'),
  });
}

// ============================================================================
// Audit
// ============================================================================

type AuditIndexQuery = paths['/audit']['get']['parameters']['query'];

export function useAdminAudit(query?: AuditIndexQuery) {
  const api = useRbacApi();

  return useQuery({
    queryKey: ['admin', 'audit', query ?? {}],
    queryFn: () => unwrap(api.GET('/audit', { params: { query } })),
  });
}

export type { AuditIndexQuery };

// Convenience type re-exports for hosts that want strongly-typed callers.
export type { Module, Role, Language, AuditEntry };
