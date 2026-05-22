<?php

declare(strict_types=1);

namespace Modularize\Access\Laravel\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('admin.roles.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'display_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'translations' => ['sometimes', 'array'],
            'translations.*' => ['array'],
        ];
    }
}
