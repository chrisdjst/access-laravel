<?php

declare(strict_types=1);

namespace ModularizeRbac\Laravel\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use ModularizeRbac\Core\Application\Module\BulkCreateModules\BulkCreateModules;
use ModularizeRbac\Core\Application\Module\BulkCreateModules\BulkCreateModulesInput;
use ModularizeRbac\Core\Application\Module\BulkDeleteModules\BulkDeleteModules;
use ModularizeRbac\Core\Application\Module\BulkDeleteModules\BulkDeleteModulesInput;
use ModularizeRbac\Core\Application\Module\CreateModule\CreateModule;
use ModularizeRbac\Core\Application\Module\CreateModule\CreateModuleInput;
use ModularizeRbac\Core\Application\Module\DeleteModule\DeleteModule;
use ModularizeRbac\Core\Application\Module\ListModules\ListModules;
use ModularizeRbac\Core\Application\Module\ListModules\ListModulesPaginated;
use ModularizeRbac\Core\Application\Module\ModuleFilter;
use ModularizeRbac\Core\Application\Module\ModuleOutput;
use ModularizeRbac\Core\Application\Module\ShowModule\ShowModule;
use ModularizeRbac\Core\Application\Module\UpdateModule\UpdateModule;
use ModularizeRbac\Core\Application\Module\UpdateModule\UpdateModuleInput;
use ModularizeRbac\Core\Application\Ports\LanguageRepository;
use ModularizeRbac\Core\Application\Ports\TranslationRepository;
use ModularizeRbac\Core\Application\Shared\Pagination;
use ModularizeRbac\Core\Domain\Shared\Uuid;
use ModularizeRbac\Laravel\Http\Requests\BulkCreateModulesRequest;
use ModularizeRbac\Laravel\Http\Requests\BulkDeleteModulesRequest;
use ModularizeRbac\Laravel\Http\Requests\StoreModuleRequest;
use ModularizeRbac\Laravel\Http\Requests\UpdateModuleRequest;
use ModularizeRbac\Laravel\Http\Resources\ModuleResource;
use ModularizeRbac\Laravel\Models\ModulePrice as ModulePriceEloquent;
use ModularizeRbac\Laravel\Translations\TranslationApplier;

/**
 * Thin HTTP controller — delegates to access-core use-cases and
 * envelopes their output in JsonResource. Translations and price are
 * pulled from the read side at the boundary; the use-case layer
 * stays oblivious to the HTTP payload shape.
 */
class ModuleController extends Controller
{
    public function __construct(
        private readonly ListModules $listModules,
        private readonly ListModulesPaginated $listModulesPaginated,
        private readonly ShowModule $showModule,
        private readonly CreateModule $createModule,
        private readonly UpdateModule $updateModule,
        private readonly DeleteModule $deleteModuleUseCase,
        private readonly BulkCreateModules $bulkCreateModules,
        private readonly BulkDeleteModules $bulkDeleteModules,
        private readonly TranslationApplier $translations,
        private readonly TranslationRepository $translationRepository,
        private readonly LanguageRepository $languageRepository,
    ) {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        // When the caller passes any pagination or filter param, route
        // through the paginated use-case + attach a `meta` envelope.
        // Otherwise preserve the v2.4.x contract of returning the full
        // tree (this is the backwards-compat path).
        $paginating = $request->hasAny(['limit', 'offset', 'is_active', 'root_module_id', 'slug_like']);

        if ($paginating) {
            $filter = new ModuleFilter(
                isActive: $request->has('is_active') ? $request->boolean('is_active') : null,
                rootModuleId: $request->query('root_module_id'),
                slugLike: $request->query('slug_like'),
            );
            $pagination = new Pagination(
                limit: $request->has('limit') ? (int) $request->query('limit') : null,
                offset: $request->has('offset') ? (int) $request->query('offset') : null,
            );

            $page = $this->listModulesPaginated->execute($filter, $pagination);
            $resources = [];
            foreach ($page->items as $out) {
                $resources[] = $this->enrich($out);
            }

            return ModuleResource::collection($resources)->additional([
                'meta' => [
                    'total' => $page->total,
                    'limit' => $page->pagination->limit,
                    'offset' => $page->pagination->offset,
                ],
            ]);
        }

        $resources = [];
        foreach ($this->listModules->execute() as $out) {
            $resources[] = $this->enrich($out);
        }

        return ModuleResource::collection($resources)->additional([
            'meta' => ['count' => count($resources)],
        ]);
    }

    public function show(string $id): ModuleResource
    {
        return new ModuleResource($this->enrich($this->showModule->execute($id)));
    }

    public function store(StoreModuleRequest $request): JsonResponse
    {
        $data = $request->validated();
        $payloadTranslations = $data['translations'] ?? [];
        unset($data['translations']);

        $output = $this->createModule->execute(new CreateModuleInput(
            slug: (string) $data['slug'],
            name: (string) $data['name'],
            redirect: $data['redirect'] ?? null,
            icon: $data['icon'] ?? null,
            rootModuleId: $data['root_module_id'] ?? null,
            sortOrder: (int) ($data['sort_order'] ?? 0),
            isActive: (bool) ($data['is_active'] ?? true),
        ));

        if (is_array($payloadTranslations) && $payloadTranslations !== []) {
            $this->translations->apply('module', new Uuid($output->id), $payloadTranslations);
        }

        return (new ModuleResource($this->enrich($output)))->response()->setStatusCode(201);
    }

    public function update(UpdateModuleRequest $request, string $id): ModuleResource
    {
        $data = $request->validated();
        $payloadTranslations = $data['translations'] ?? null;
        unset($data['translations']);

        $output = $this->updateModule->execute(new UpdateModuleInput(
            id: $id,
            name: (string) ($data['name'] ?? ''),
            redirect: $data['redirect'] ?? null,
            icon: $data['icon'] ?? null,
            rootModuleId: $data['root_module_id'] ?? null,
            sortOrder: (int) ($data['sort_order'] ?? 0),
            isActive: (bool) ($data['is_active'] ?? false),
        ));

        if (is_array($payloadTranslations)) {
            $this->translations->apply('module', new Uuid($output->id), $payloadTranslations);
        }

        return new ModuleResource($this->enrich($output));
    }

    public function destroy(string $id): JsonResponse
    {
        $this->deleteModuleUseCase->execute($id);

        return response()->json(null, 204);
    }

    public function bulkStore(BulkCreateModulesRequest $request): JsonResponse
    {
        /** @var list<array<string, mixed>> $modules */
        $modules = $request->validated('modules', []);

        $outputs = $this->bulkCreateModules->execute(new BulkCreateModulesInput($modules));

        $resources = array_map(fn (ModuleOutput $out) => $this->enrich($out), $outputs);

        return ModuleResource::collection($resources)->response()->setStatusCode(201);
    }

    public function bulkDestroy(BulkDeleteModulesRequest $request): JsonResponse
    {
        /** @var list<string> $ids */
        $ids = $request->validated('ids', []);

        $this->bulkDeleteModules->execute(new BulkDeleteModulesInput($ids));

        return response()->json(null, 204);
    }

    /**
     * @return array{output: ModuleOutput, translations: array<string, array<string, string>>, price: array{value: float, currency: string}|null}
     */
    private function enrich(ModuleOutput $output): array
    {
        $translations = $this->translationRepository->forSubject('module', new Uuid($output->id));
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

        $price = ModulePriceEloquent::query()
            ->where('module_id', $output->id)
            ->where('is_active', true)
            ->first();

        return [
            'output' => $output,
            'translations' => $grouped,
            'price' => $price !== null ? [
                'value' => (float) $price->value,
                'currency' => (string) $price->currency,
            ] : null,
        ];
    }
}
