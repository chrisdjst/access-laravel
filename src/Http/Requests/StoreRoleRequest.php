<?php

declare(strict_types=1);

namespace ModularizeRbac\Laravel\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRoleRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:100', 'regex:/^[a-z][a-z0-9_-]*$/'],
            'display_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'guard_name' => ['required', 'string', 'max:64'],
            'organization_id' => ['sometimes', 'nullable', 'uuid'],
            'level' => ['sometimes', 'integer', 'min:0'],
            'is_system' => ['sometimes', 'boolean'],
            'parent_role_id' => ['sometimes', 'nullable', 'uuid'],
            'translations' => ['sometimes', 'array'],
            'translations.*' => ['array'],
        ];
    }
}
