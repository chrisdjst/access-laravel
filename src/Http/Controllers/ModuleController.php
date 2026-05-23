<?php

declare(strict_types=1);

namespace Modularize\Access\Laravel\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Modularize\Access\Application\Module\CreateModule\CreateModule;
use Modularize\Access\Application\Module\CreateModule\CreateModuleInput;
use Modularize\Access\Application\Module\DeleteModule\DeleteModule;
use Modularize\Access\Application\Module\ListModules\ListModules;
use Modularize\Access\Application\Module\ModuleOutput;
use Modularize\Access\Application\Module\ShowModule\ShowModule;
use Modularize\Access\Application\Module\UpdateModule\UpdateModule;
use Modularize\Access\Application\Module\UpdateModule\UpdateModuleInput;
use Modularize\Access\Application\Ports\LanguageRepository;
use Modularize\Access\Application\Ports\TranslationRepository;
use Modularize\Access\Domain\Shared\Uuid;
use Modularize\Access\Laravel\Http\Requests\StoreModuleRequest;
use Modularize\Access\Laravel\Http\Requests\UpdateModuleRequest;
use Modularize\Access\Laravel\Http\Resources\ModuleResource;
use Modularize\Access\Laravel\Models\ModulePrice as ModulePriceEloquent;
use Modularize\Access\Laravel\Translations\TranslationApplier;

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
        private readonly ShowModule $showModule,
        private readonly CreateModule $createModule,
        private readonly UpdateModule $updateModule,
        private readonly DeleteModule $deleteModuleUseCase,
        private readonly TranslationApplier $translations,
        private readonly TranslationRepository $translationRepository,
        private readonly LanguageRepository $languageRepository,
    ) {
    }

    public function index(): AnonymousResourceCollection
    {
        $resources = [];
        foreach ($this->listModules->execute() as $out) {
            $resources[] = $this->enrich($out);
        }

        return ModuleResource::collection($resources);
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
