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
export {
  LanguagesAdmin,
  type LanguagesAdminProps,
  type LanguagesAdminLabels,
} from './components/LanguagesAdmin.js';
export {
  AuditViewer,
  type AuditViewerProps,
  type AuditViewerLabels,
} from './components/AuditViewer.js';

// React Query hooks
export {
  useAdminAudit,
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
  type AuditEntry,
  type AuditIndexQuery,
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
