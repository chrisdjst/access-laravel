export { VERSION } from './version';

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
} from './types';

// API factory
export { createRbacApi, type HttpClient, type RbacApi } from './api/rbac';

// Provider + hook
export { RbacProvider, useRbacApi } from './provider';

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
} from './hooks/useRbac';
