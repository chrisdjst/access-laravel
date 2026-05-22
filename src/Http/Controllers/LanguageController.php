<?php

declare(strict_types=1);

namespace Modularize\Access\Laravel\Http\Controllers;

use Modularize\Access\Laravel\Http\Requests\StoreLanguageRequest;
use Modularize\Access\Laravel\Http\Requests\UpdateLanguageRequest;
use Modularize\Access\Laravel\Http\Resources\LanguageResource;
use Modularize\Access\Laravel\Models\Language;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class LanguageController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        abort_unless($request->user()->can('admin.languages.view'), 403);

        return LanguageResource::collection(Language::orderBy('name')->get());
    }

    public function store(StoreLanguageRequest $request): JsonResponse
    {
        $data = $request->validated();
        if (! empty($data['is_default'])) {
            Language::where('is_default', true)->update(['is_default' => false]);
        }
        $language = Language::create($data);

        return (new LanguageResource($language))->response()->setStatusCode(201);
    }

    public function show(Request $request, Language $language): LanguageResource
    {
        abort_unless($request->user()->can('admin.languages.view'), 403);

        return new LanguageResource($language);
    }

    public function update(UpdateLanguageRequest $request, Language $language): LanguageResource
    {
        $data = $request->validated();
        DB::transaction(function () use ($data, $language): void {
            if (! empty($data['is_default'])) {
                Language::where('is_default', true)
                    ->where('id', '!=', $language->id)
                    ->update(['is_default' => false]);
            }
            $language->update($data);
        });

        return new LanguageResource($language);
    }

    public function destroy(Request $request, Language $language): JsonResponse
    {
        abort_unless($request->user()->can('admin.languages.manage'), 403);
        abort_if($language->is_default, 422, 'NÃ£o Ã© possÃ­vel remover o idioma padrÃ£o.');

        $language->delete();

        return response()->json(null, 204);
    }

    public function setDefault(Request $request, Language $language): LanguageResource
    {
        abort_unless($request->user()->can('admin.languages.manage'), 403);

        DB::transaction(function () use ($language): void {
            Language::where('is_default', true)->update(['is_default' => false]);
            $language->update(['is_default' => true]);
        });

        return new LanguageResource($language);
    }
}
