<?php

declare(strict_types=1);

namespace ModularizeRbac\Laravel\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use ModularizeRbac\Core\Application\Ports\LanguageRepository;
use ModularizeRbac\Core\Application\Ports\RoleModulePermissionRepository;
use ModularizeRbac\Core\Application\Ports\TranslationRepository;
use ModularizeRbac\Core\Application\Role\AssignUsersToRole\AssignUsersToRole;
use ModularizeRbac\Core\Application\Role\AssignUsersToRole\AssignUsersToRoleInput;
use ModularizeRbac\Core\Application\Role\CloneRole\CloneRole;
use ModularizeRbac\Core\Application\Role\CloneRole\CloneRoleInput;
use ModularizeRbac\Core\Application\Role\CreateRole\CreateRole;
use ModularizeRbac\Core\Application\Role\CreateRole\CreateRoleInput;
use ModularizeRbac\Core\Application\Role\DeleteRole\DeleteRole;
use ModularizeRbac\Core\Application\Role\GetRolePermissionMatrix\GetRolePermissionMatrix;
use ModularizeRbac\Core\Application\Role\ListRoles\ListRoles;
use ModularizeRbac\Core\Application\Role\ListRoles\ListRolesPaginated;
use ModularizeRbac\Core\Application\Role\RoleFilter;
use ModularizeRbac\Core\Application\Role\RoleOutput;
use ModularizeRbac\Core\Application\Shared\Pagination;
use ModularizeRbac\Core\Application\Role\ShowRole\ShowRole;
use ModularizeRbac\Core\Application\Role\SyncRoleModules\SyncRoleModules;
use ModularizeRbac\Core\Application\Role\SyncRoleModules\SyncRoleModulesInput;
use ModularizeRbac\Core\Application\Role\UpdateRole\UpdateRole;
use ModularizeRbac\Core\Application\Role\UpdateRole\UpdateRoleInput;
use ModularizeRbac\Core\Domain\Shared\Uuid;
use ModularizeRbac\Laravel\Http\Requests\AssignUsersToRoleRequest;
use ModularizeRbac\Laravel\Http\Requests\CloneRoleRequest;
use ModularizeRbac\Laravel\Http\Requests\StoreRoleRequest;
use ModularizeRbac\Laravel\Http\Requests\SyncRoleModulesRequest;
use ModularizeRbac\Laravel\Http\Requests\UpdateRoleRequest;
use ModularizeRbac\Laravel\Http\Resources\RoleResource;
use ModularizeRbac\Laravel\Http\Resources\RolePermissionMatrixResource;
use ModularizeRbac\Laravel\Translations\TranslationApplier;

/**
 * Thin HTTP controller for roles. Delegates to access-core use-cases
 * and enriches the response with translations + role-module matrix
 * for backwards compatibility with the v0.1.0 API contract.
 */
class RoleController extends Controller
{
    public function __construct(
        private readonly ListRoles $listRoles,
        private readonly ListRolesPaginated $listRolesPaginated,
        private readonly ShowRole $showRole,
        private readonly CreateRole $createRoleUseCase,
        private readonly UpdateRole $updateRoleUseCase,
        private readonly DeleteRole $deleteRoleUseCase,
        private readonly CloneRole $cloneRoleUseCase,
        private readonly AssignUsersToRole $assignUsersToRoleUseCase,
        private readonly SyncRoleModules $syncRoleModules,
        private readonly GetRolePermissionMatrix $rolePermissionMatrix,
        private readonly TranslationApplier $translations,
        private readonly TranslationRepository $translationRepository,
        private readonly LanguageRepository $languageRepository,
        private readonly RoleModulePermissionRepository $bindings,
    ) {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $paginating = $request->hasAny([
            'limit', 'offset', 'is_system', 'level_min', 'level_max', 'has_parent',
        ]);

        if ($paginating) {
            $filter = new RoleFilter(
                guard: $request->query('guard'),
                tenantId: $request->query('organization_id'),
                tenantPresent: $request->has('organization_id'),
                isSystem: $request->has('is_system') ? $request->boolean('is_system') : null,
                levelMin: $request->has('level_min') ? (int) $request->query('level_min') : null,
                levelMax: $request->has('level_max') ? (int) $request->query('level_max') : null,
                hasParent: $request->has('has_parent') ? $request->boolean('has_parent') : null,
            );
            $pagination = new Pagination(
                limit: $request->has('limit') ? (int) $request->query('limit') : null,
                offset: $request->has('offset') ? (int) $request->query('offset') : null,
            );

            $page = $this->listRolesPaginated->execute($filter, $pagination);
            $resources = [];
            foreach ($page->items as $out) {
                $resources[] = $this->enrich($out);
            }

            return RoleResource::collection($resources)->additional([
                'meta' => [
                    'total' => $page->total,
                    'limit' => $page->pagination->limit,
                    'offset' => $page->pagination->offset,
                ],
            ]);
        }

        $outputs = $this->listRoles->execute(
            $request->query('guard'),
            $request->query('organization_id'),
        );
        $resources = [];
        foreach ($outputs as $out) {
            $resources[] = $this->enrich($out);
        }

        return RoleResource::collection($resources);
    }

    public function show(string $role): RoleResource
    {
        return new RoleResource($this->enrich($this->showRole->execute($role)));
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        $data = $request->validated();
        $payloadTranslations = $data['translations'] ?? null;
        unset($data['translations']);

        $output = $this->createRoleUseCase->execute(new CreateRoleInput(
            name: (string) $data['name'],
            displayName: $data['display_name'] ?? null,
            guard: (string) $data['guard_name'],
            tenantId: $data['organization_id'] ?? null,
            level: (int) ($data['level'] ?? 0),
            isSystem: (bool) ($data['is_system'] ?? false),
            parentRoleId: $data['parent_role_id'] ?? null,
        ));

        if (is_array($payloadTranslations)) {
            $this->translations->apply('role', new Uuid($output->id), $payloadTranslations);
        }

        return (new RoleResource($this->enrich($output)))->response()->setStatusCode(201);
    }

    public function destroy(string $role): JsonResponse
    {
        $this->deleteRoleUseCase->execute($role);

        return response()->json(null, 204);
    }

    public function clone(CloneRoleRequest $request, string $role): JsonResponse
    {
        $data = $request->validated();

        $output = $this->cloneRoleUseCase->execute(new CloneRoleInput(
            sourceRoleId: $role,
            name: (string) $data['name'],
            displayName: $data['display_name'] ?? null,
        ));

        return (new RoleResource($this->enrich($output)))->response()->setStatusCode(201);
    }

    public function bulkAssignUsers(AssignUsersToRoleRequest $request, string $role): RoleResource
    {
        /** @var list<string> $userIds */
        $userIds = $request->validated('user_ids', []);

        $output = $this->assignUsersToRoleUseCase->execute(new AssignUsersToRoleInput(
            roleId: $role,
            userIds: $userIds,
            tenantId: $request->validated('organization_id'),
        ));

        return new RoleResource($this->enrich($output));
    }

    public function permissionMatrix(string $role): RolePermissionMatrixResource
    {
        return new RolePermissionMatrixResource($this->rolePermissionMatrix->execute($role));
    }

    public function update(UpdateRoleRequest $request, string $role): RoleResource
    {
        $data = $request->validated();
        $payloadTranslations = $data['translations'] ?? null;
        unset($data['translations']);

        $output = $this->updateRoleUseCase->execute(new UpdateRoleInput(
            id: $role,
            displayName: $data['display_name'] ?? null,
        ));

        if (is_array($payloadTranslations)) {
            $this->translations->apply('role', new Uuid($output->id), $payloadTranslations);
        }

        return new RoleResource($this->enrich($output));
    }

    public function syncModules(SyncRoleModulesRequest $request, string $role): RoleResource
    {
        $data = $request->validated();
        /** @var list<array<string, mixed>> $modules */
        $modules = $data['modules'] ?? [];

        $output = $this->syncRoleModules->execute(new SyncRoleModulesInput(
            roleId: $role,
            modules: $modules,
        ));

        return new RoleResource($this->enrich($output));
    }

    /**
     * @return array{output: RoleOutput, translations: array<string, array<string, string>>, modules: list<array<string, mixed>>}
     */
    private function enrich(RoleOutput $output): array
    {
        $roleId = new Uuid($output->id);
        $translations = $this->translationRepository->forSubject('role', $roleId);
        $languagesById = [];
        foreach ($this->languageRepository->all() as $lang) {
            $languagesById[$lang->id->value] = $lang->code()->value;
        }
        $grouped = [];
        foreach ($translations as $t) {
            $locale = $languagesById[$t->languageId->value] ?? null;
            if ($locale === null) {
                continue;
            }
            $grouped[$t->field][$locale] = $t->value();
        }

        $modules = [];
        foreach ($this->bindings->forRole($roleId) as $row) {
            $binding = $row['binding'];
            $perm = $row['permission'];
            $modules[] = [
                'module_id' => $binding->moduleId->value,
                'module_permission_id' => $perm->id->value,
                'flags' => [
                    'is_listing_allowed' => $perm->isListingAllowed(),
                    'is_reading_allowed' => $perm->isReadingAllowed(),
                    'is_writing_allowed' => $perm->isWritingAllowed(),
                    'is_editing_allowed' => $perm->isEditingAllowed(),
                    'is_delete_allowed' => $perm->isDeleteAllowed(),
                ],
            ];
        }

        return [
            'output' => $output,
            'translations' => $grouped,
            'modules' => $modules,
        ];
    }
}
