export { VERSION } from './version.js';

// Types
export type {
  AdminLanguage,
  AdminModule,
  AdminRole,
  LanguagePayload,
  RoleModuleEntry,
  SyncRoleModulesPayload,
  UpdateModulePayload,
  UpdateRolePayload,
} from './types/index.js';

// API factory
export { createRbacApi, type HttpClient, type RbacApi } from './api/rbac.js';

// Provider + hook
export { RbacProvider, useRbacApi } from './provider.js';

// React Query hooks
export {
  useAdminLanguages,
  useAdminModules,
  useAdminRole,
  useAdminRoles,
  useCreateLanguage,
  useDeleteLanguage,
  useSetDefaultLanguage,
  useSyncRoleModules,
  useUpdateLanguage,
  useUpdateModule,
  useUpdateRole,
  type MutationCallbacks,
} from './hooks/useRbac.js';
