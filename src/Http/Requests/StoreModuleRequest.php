<?php

declare(strict_types=1);

namespace Casamento\Rbac\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreModuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('admin.modules.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'slug' => ['required', 'string', 'max:50', 'unique:modules,slug', 'regex:/^[a-z0-9_-]+$/'],
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
