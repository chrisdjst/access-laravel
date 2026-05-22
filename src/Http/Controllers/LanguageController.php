<?php

declare(strict_types=1);

namespace Modularize\Access\Laravel\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Modularize\Access\Application\Language\CreateLanguage\CreateLanguage;
use Modularize\Access\Application\Language\CreateLanguage\CreateLanguageInput;
use Modularize\Access\Application\Language\DeleteLanguage\DeleteLanguage;
use Modularize\Access\Application\Language\ListLanguages\ListLanguages;
use Modularize\Access\Application\Language\SetDefaultLanguage\SetDefaultLanguage;
use Modularize\Access\Application\Language\ShowLanguage\ShowLanguage;
use Modularize\Access\Application\Language\UpdateLanguage\UpdateLanguage;
use Modularize\Access\Application\Language\UpdateLanguage\UpdateLanguageInput;
use Modularize\Access\Laravel\Http\Requests\StoreLanguageRequest;
use Modularize\Access\Laravel\Http\Requests\UpdateLanguageRequest;
use Modularize\Access\Laravel\Http\Resources\LanguageResource;

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
