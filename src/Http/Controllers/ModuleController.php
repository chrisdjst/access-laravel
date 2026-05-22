<?php

declare(strict_types=1);

namespace Modularize\Access\Laravel\Http\Controllers;

use Modularize\Access\Laravel\Http\Requests\StoreModuleRequest;
use Modularize\Access\Laravel\Http\Requests\UpdateModuleRequest;
use Modularize\Access\Laravel\Http\Resources\ModuleResource;
use Modularize\Access\Laravel\Models\Module;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

class ModuleController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        abort_unless($request->user()->can('admin.modules.view'), 403);

        $modules = Module::with(['translations.language', 'price'])
            ->orderBy('sort_order')
            ->get();

        return ModuleResource::collection($modules);
    }

    public function show(Request $request, Module $module): ModuleResource
    {
        abort_unless($request->user()->can('admin.modules.view'), 403);

        return new ModuleResource($module->load(['translations.language', 'price']));
    }

    public function store(StoreModuleRequest $request): JsonResponse
    {
        $data = $request->validated();
        $translations = $data['translations'] ?? [];
        unset($data['translations']);

        $data['created_by'] = $request->user()?->id;
        $module = Module::create($data);

        if ($translations) {
            $module->setTranslationsBulk($translations);
        }

        return (new ModuleResource($module->load(['translations.language', 'price'])))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateModuleRequest $request, Module $module): ModuleResource
    {
        $data = $request->validated();
        $translations = $data['translations'] ?? null;
        unset($data['translations']);

        $data['updated_by'] = $request->user()?->id;
        $module->update($data);

        if ($translations !== null) {
            $module->setTranslationsBulk($translations);
        }

        return new ModuleResource($module->load(['translations.language', 'price']));
    }

    public function destroy(Request $request, Module $module): JsonResponse
    {
        abort_unless($request->user()->can('admin.modules.manage'), 403);
        $module->delete();

        return response()->json(null, 204);
    }
}
