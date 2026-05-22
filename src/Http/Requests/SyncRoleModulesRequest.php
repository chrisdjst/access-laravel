<?php

declare(strict_types=1);

namespace Modularize\Access\Laravel\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SyncRoleModulesRequest extends FormRequest
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
            'modules' => ['required', 'array'],
            'modules.*.module_id' => ['required', 'uuid', 'exists:modules,id'],
            'modules.*.is_reading_allowed' => ['sometimes', 'boolean'],
            'modules.*.is_writing_allowed' => ['sometimes', 'boolean'],
            'modules.*.is_editing_allowed' => ['sometimes', 'boolean'],
            'modules.*.is_delete_allowed' => ['sometimes', 'boolean'],
            'modules.*.is_listing_allowed' => ['sometimes', 'boolean'],
        ];
    }
}
