<?php

declare(strict_types=1);

namespace ModularizeRbac\Laravel\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use ModularizeRbac\Core\Application\Language\CreateLanguage\CreateLanguage;
use ModularizeRbac\Core\Application\Language\CreateLanguage\CreateLanguageInput;
use ModularizeRbac\Core\Application\Language\DeleteLanguage\DeleteLanguage;
use ModularizeRbac\Core\Application\Language\ListLanguages\ListLanguages;
use ModularizeRbac\Core\Application\Language\SetDefaultLanguage\SetDefaultLanguage;
use ModularizeRbac\Core\Application\Language\ShowLanguage\ShowLanguage;
use ModularizeRbac\Core\Application\Language\UpdateLanguage\UpdateLanguage;
use ModularizeRbac\Core\Application\Language\UpdateLanguage\UpdateLanguageInput;
use ModularizeRbac\Laravel\Http\Requests\StoreLanguageRequest;
use ModularizeRbac\Laravel\Http\Requests\UpdateLanguageRequest;
use ModularizeRbac\Laravel\Http\Resources\LanguageResource;

/**
 * Thin HTTP controller — delegates to access-core language use-cases.
 */
class LanguageController extends Controller
{
    public function __construct(
        private readonly ListLanguages $listLanguages,
        private readonly ShowLanguage $showLanguage,
        private readonly CreateLanguage $createLanguage,
        private readonly UpdateLanguage $updateLanguageUseCase,
        private readonly DeleteLanguage $deleteLanguageUseCase,
        private readonly SetDefaultLanguage $setDefaultLanguageUseCase,
    ) {
    }

    public function index(): AnonymousResourceCollection
    {
        return LanguageResource::collection($this->listLanguages->execute());
    }

    public function show(string $language): LanguageResource
    {
        return new LanguageResource($this->showLanguage->execute($language));
    }

    public function store(StoreLanguageRequest $request): JsonResponse
    {
        $data = $request->validated();
        $output = $this->createLanguage->execute(new CreateLanguageInput(
            code: (string) $data['code'],
            name: (string) $data['name'],
            isDefault: (bool) ($data['is_default'] ?? false),
            isActive: (bool) ($data['is_active'] ?? true),
        ));

        return (new LanguageResource($output))->response()->setStatusCode(201);
    }

    public function update(UpdateLanguageRequest $request, string $language): LanguageResource
    {
        $data = $request->validated();
        $output = $this->updateLanguageUseCase->execute(new UpdateLanguageInput(
            id: $language,
            code: (string) ($data['code'] ?? ''),
            name: (string) ($data['name'] ?? ''),
            isActive: (bool) ($data['is_active'] ?? true),
        ));

        return new LanguageResource($output);
    }

    public function destroy(string $language): JsonResponse
    {
        $this->deleteLanguageUseCase->execute($language);

        return response()->json(null, 204);
    }

    public function setDefault(string $language): LanguageResource
    {
        return new LanguageResource($this->setDefaultLanguageUseCase->execute($language));
    }
}
