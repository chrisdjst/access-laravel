export { VERSION } from './version.js';

// API factory + spec-derived types (re-exported from sdk-ts).
export {
  createRbacApi,
  type RbacApi,
  type CreateClientOptions,
  type ModulesIndexResponse,
  type paths,
  type components,
} from './api/rbac.js';

// Provider + hook
export { RbacProvider, useRbacApi } from './provider.js';

// Components
export { RolesPage, type RolesPageProps, type RolesPageLabels } from './components/RolesPage.js';

// React Query hooks
export {
  useAdminLanguages,
  useAdminModules,
  useAdminRole,
  useAdminRoles,
  useCloneRole,
  useCreateLanguage,
  useCreateRole,
  useDeleteLanguage,
  useDeleteRole,
  useRestoreRole,
  useSetDefaultLanguage,
  useSyncRoleModules,
  useUpdateLanguage,
  useUpdateModule,
  useUpdateRole,
  type Language,
  type Module,
  type MutationCallbacks,
  type Role,
} from './hooks/useRbac.js';
