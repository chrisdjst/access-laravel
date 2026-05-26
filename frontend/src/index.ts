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

// React Query hooks
export {
  useAdminLanguages,
  useAdminModules,
  useAdminRole,
  useAdminRoles,
  useBulkDeleteModules,
  useCreateLanguage,
  useCreateModule,
  useDeleteLanguage,
  useDeleteModule,
  useSetDefaultLanguage,
  useSyncRoleModules,
  useUpdateLanguage,
  useUpdateModule,
  useUpdateRole,
  type BulkDeleteModulesPayload,
  type CreateModulePayload,
  type Language,
  type Module,
  type MutationCallbacks,
  type Role,
} from './hooks/useRbac.js';

// Components
export {
  ModulesTreeEditor,
  type ModulesTreeEditorProps,
  type ModulesTreeEditorLabels,
} from './components/ModulesTreeEditor.js';
