<?php

declare(strict_types=1);

namespace ModularizeRbac\Laravel\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use ModularizeRbac\Core\Application\Ports\LanguageRepository;
use ModularizeRbac\Core\Application\Ports\RoleModulePermissionRepository;
use ModularizeRbac\Core\Application\Ports\TranslationRepository;
use ModularizeRbac\Core\Application\Role\ListRoles\ListRoles;
use ModularizeRbac\Core\Application\Role\RoleOutput;
use ModularizeRbac\Core\Application\Role\ShowRole\ShowRole;
use ModularizeRbac\Core\Application\Role\SyncRoleModules\SyncRoleModules;
use ModularizeRbac\Core\Application\Role\SyncRoleModules\SyncRoleModulesInput;
use ModularizeRbac\Core\Application\Role\UpdateRole\UpdateRole;
use ModularizeRbac\Core\Application\Role\UpdateRole\UpdateRoleInput;
use ModularizeRbac\Core\Domain\Shared\Uuid;
use ModularizeRbac\Laravel\Http\Requests\SyncRoleModulesRequest;
use ModularizeRbac\Laravel\Http\Requests\UpdateRoleRequest;
use ModularizeRbac\Laravel\Http\Resources\RoleResource;
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
        private readonly ShowRole $showRole,
        private readonly UpdateRole $updateRoleUseCase,
        private readonly SyncRoleModules $syncRoleModules,
        private readonly TranslationApplier $translations,
        private readonly TranslationRepository $translationRepository,
        private readonly LanguageRepository $languageRepository,
        private readonly RoleModulePermissionRepository $bindings,
    ) {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
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
