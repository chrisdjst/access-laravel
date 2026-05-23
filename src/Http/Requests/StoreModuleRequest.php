<?php

declare(strict_types=1);

namespace ModularizeRbac\Laravel\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreModuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // The slug regex allows dots so nested module slugs like
            // "admin.events" (matching the ModuleSlug VO) round-trip
            // through the API. The unique check stays at the DB level.
            'slug' => ['required', 'string', 'max:100', 'unique:modules,slug', 'regex:/^[a-z0-9]+(\.[a-z0-9]+)*$/'],
            'name' => ['required', 'string', 'max:100'],
            'icon' => ['nullable', 'string', 'max:50'],
            'redirect' => ['nullable', 'string', 'max:255'],
            'root_module_id' => ['nullable', 'uuid', 'exists:modules,id'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'translations' => ['sometimes', 'array'],
            'translations.*' => ['array'],
        ];
    }
}
