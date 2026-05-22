<?php

declare(strict_types=1);

namespace Modularize\Access\Laravel\Http\Controllers;

use Modularize\Access\Laravel\Http\Requests\SyncRoleModulesRequest;
use Modularize\Access\Laravel\Http\Requests\UpdateRoleRequest;
use Modularize\Access\Laravel\Http\Resources\RoleResource;
use Modularize\Access\Laravel\Models\ModulePermission;
use Modularize\Access\Laravel\Models\Role;
use Modularize\Access\Laravel\Models\RoleModulePermission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class RoleController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        abort_unless($request->user()->can('admin.roles.view'), 403);

        $roles = Role::query()
            ->when($request->query('guard'), fn ($q, $g) => $q->where('guard_name', $g))
            ->when($request->query('organization_id'), fn ($q, $o) => $q->where('organization_id', $o))
            ->orderByDesc('level')
            ->orderBy('name')
            ->with(['rolePermissions.permission', 'translations.language'])
            ->get();

        return RoleResource::collection($roles);
    }

    public function show(Request $request, Role $role): RoleResource
    {
        abort_unless($request->user()->can('admin.roles.view'), 403);

        return new RoleResource($role->load(['rolePermissions.permission', 'translations.language']));
    }

    public function update(UpdateRoleRequest $request, Role $role): RoleResource
    {
        $data = $request->validated();
        $translations = $data['translations'] ?? null;
        unset($data['translations']);

        if (array_key_exists('display_name', $data)) {
            $role->update(['display_name' => $data['display_name']]);
        }

        if ($translations !== null) {
            $role->setTranslationsBulk($translations);
        }

        return new RoleResource($role->load(['rolePermissions.permission', 'translations.language']));
    }

    /**
     * Sync the complete set of module permissions for this role.
     * Payload: { modules: [{ module_id, is_reading_allowed, ... }, ...] }.
     */
    public function syncModules(SyncRoleModulesRequest $request, Role $role): RoleResource
    {
        $data = $request->validated();
        $userId = $request->user()?->id;

        DB::transaction(function () use ($data, $role, $userId): void {
            $keptModuleIds = [];

            foreach ($data['modules'] as $m) {
                $keptModuleIds[] = $m['module_id'];

                $permission = ModulePermission::create([
                    'is_reading_allowed' => $m['is_reading_allowed'] ?? false,
                    'is_writing_allowed' => $m['is_writing_allowed'] ?? false,
                    'is_editing_allowed' => $m['is_editing_allowed'] ?? false,
                    'is_delete_allowed' => $m['is_delete_allowed'] ?? false,
                    'is_listing_allowed' => $m['is_listing_allowed'] ?? false,
                    'is_active' => true,
                    'created_by' => $userId,
                ]);

                RoleModulePermission::updateOrCreate(
                    ['role_id' => $role->id, 'module_id' => $m['module_id']],
                    [
                        'module_permission_id' => $permission->id,
                        'updated_by' => $userId,
                    ],
                );
            }

            // Remove modules that were not in the payload â€” fires observer
            // per row to revoke the matching Spatie permissions.
            RoleModulePermission::where('role_id', $role->id)
                ->whereNotIn('module_id', $keptModuleIds)
                ->get()
                ->each
                ->delete();
        });

        return new RoleResource($role->load('rolePermissions.permission'));
    }
}
