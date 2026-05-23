<?php

declare(strict_types=1);

namespace ModularizeRbac\Laravel\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateModuleRequest extends FormRequest
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
        $module = $this->route('module');
        $id = is_object($module) ? $module->id : $module;

        return [
            // Slug is immutable through the use-case; accepted here
            // only to ease backwards compat for clients that submit
            // the full row.
            'slug' => ['sometimes', 'string', 'max:100', 'regex:/^[a-z0-9]+(\.[a-z0-9]+)*$/', Rule::unique('modules', 'slug')->ignore($id)],
            'name' => ['sometimes', 'string', 'max:100'],
            'icon' => ['sometimes', 'nullable', 'string', 'max:50'],
            'redirect' => ['sometimes', 'nullable', 'string', 'max:255'],
            'root_module_id' => ['sometimes', 'nullable', 'uuid', 'exists:modules,id'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'translations' => ['sometimes', 'array'],
            'translations.*' => ['array'],
        ];
    }
}
