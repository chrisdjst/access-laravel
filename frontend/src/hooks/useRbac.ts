import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import type { QueryClient } from '@tanstack/react-query';
import { useRbacApi } from '../provider.js';
import type {
  LanguagePayload,
  SyncRoleModulesPayload,
  UpdateModulePayload,
  UpdateRolePayload,
} from '../types/index.js';

/**
 * Optional callbacks for mutation feedback. The package stays toast-agnostic
 * (no sonner / hot-toast import) so hosts wire whichever lib they prefer.
 */
export interface MutationCallbacks {
  onSuccessMessage?: (msg: string) => void;
  onErrorMessage?: (msg: string) => void;
}

// ============================================================================
// Modules
// ============================================================================

export function useAdminModules() {
  const api = useRbacApi();
  return useQuery({
    queryKey: ['admin', 'modules'],
    queryFn: async () => (await api.listModules()).data.data,
  });
}

export function useUpdateModule(cb?: MutationCallbacks) {
  const api = useRbacApi();
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ id, payload }: { id: string; payload: UpdateModulePayload }) =>
      api.updateModule(id, payload),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['admin', 'modules'] });
      qc.invalidateQueries({ queryKey: ['me', 'modules'] });
      cb?.onSuccessMessage?.('Módulo atualizado!');
    },
    onError: () => cb?.onErrorMessage?.('Erro ao atualizar módulo.'),
  });
}

// ============================================================================
// Roles
// ============================================================================

export function useAdminRoles(guard?: string) {
  const api = useRbacApi();
  return useQuery({
    queryKey: ['admin', 'roles', { guard }],
    queryFn: async () => (await api.listRoles({ guard })).data.data,
  });
}

export function useAdminRole(id: string | null) {
  const api = useRbacApi();
  return useQuery({
    queryKey: ['admin', 'roles', id],
    queryFn: async () => (await api.getRole(id!)).data.data,
    enabled: !!id,
  });
}

export function useUpdateRole(cb?: MutationCallbacks) {
  const api = useRbacApi();
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ id, payload }: { id: string; payload: UpdateRolePayload }) =>
      api.updateRole(id, payload),
    onSuccess: (_, { id }) => {
      qc.invalidateQueries({ queryKey: ['admin', 'roles'] });
      qc.invalidateQueries({ queryKey: ['admin', 'roles', id] });
      cb?.onSuccessMessage?.('Perfil atualizado!');
    },
    onError: () => cb?.onErrorMessage?.('Erro ao atualizar perfil.'),
  });
}

export function useSyncRoleModules(cb?: MutationCallbacks) {
  const api = useRbacApi();
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ roleId, payload }: { roleId: string; payload: SyncRoleModulesPayload }) =>
      api.syncRoleModules(roleId, payload),
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
    queryFn: async () => (await api.listLanguages()).data.data,
  });
}

export function useCreateLanguage(cb?: MutationCallbacks) {
  const api = useRbacApi();
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: LanguagePayload) => api.createLanguage(payload),
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
      api.updateLanguage(id, payload),
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
    mutationFn: (id: string) => api.deleteLanguage(id),
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
    mutationFn: (id: string) => api.setDefaultLanguage(id),
    onSuccess: () => {
      invalidateLanguages(qc);
      cb?.onSuccessMessage?.('Idioma padrão atualizado!');
    },
    onError: () => cb?.onErrorMessage?.('Erro ao definir idioma padrão.'),
  });
}
