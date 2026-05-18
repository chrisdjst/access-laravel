/** A row in the modules table. Returned by GET /admin/modules. */
export interface AdminModule {
  id: string;
  slug: string;
  name: string;
  icon: string | null;
  redirect: string | null;
  root_module_id: string | null;
  sort_order: number;
  is_active: boolean;
  translations?: Record<string, Record<string, string>>;
  price?: { value: number; currency: string } | null;
}

export interface AdminLanguage {
  id: string;
  code: string;
  name: string;
  is_default: boolean;
  is_active: boolean;
}

export interface RoleModuleEntry {
  module_id: string;
  module_permission_id: string;
  flags: {
    is_reading_allowed: boolean;
    is_writing_allowed: boolean;
    is_editing_allowed: boolean;
    is_delete_allowed: boolean;
    is_listing_allowed: boolean;
  } | null;
}

export interface AdminRole {
  id: string;
  name: string;
  display_name: string | null;
  guard_name: string;
  level: number;
  is_system: boolean;
  organization_id: string | null;
  translations?: Record<string, Record<string, string>>;
  modules: RoleModuleEntry[];
}

export interface UpdateRolePayload {
  display_name?: string | null;
  translations?: Record<string, Record<string, string>>;
}

export interface LanguagePayload {
  code: string;
  name: string;
  is_default?: boolean;
  is_active?: boolean;
}

export interface UpdateModulePayload {
  name?: string;
  is_active?: boolean;
  translations?: Record<string, Record<string, string>>;
}

export interface SyncRoleModulesPayload {
  modules: Array<{
    module_id: string;
    is_reading_allowed: boolean;
    is_writing_allowed: boolean;
    is_editing_allowed: boolean;
    is_delete_allowed: boolean;
    is_listing_allowed: boolean;
  }>;
}
