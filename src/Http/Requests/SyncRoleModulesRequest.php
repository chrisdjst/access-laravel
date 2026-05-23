<?php

declare(strict_types=1);

namespace ModularizeRbac\Laravel\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SyncRoleModulesRequest extends FormRequest
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
            // Required key but accepts an empty array — that's the
            // semantic the DeleteRole use-case relies on for the
            // "drop all bindings before delete" flow.
            'modules' => ['present', 'array'],
            // Each entry MUST be an associative array — rejects
            // scalars, strings, and indexed sub-arrays.
            'modules.*' => ['array'],
            'modules.*.module_id' => ['required', 'uuid', 'exists:modules,id'],
            'modules.*.is_reading_allowed' => ['sometimes', 'boolean'],
            'modules.*.is_writing_allowed' => ['sometimes', 'boolean'],
            'modules.*.is_editing_allowed' => ['sometimes', 'boolean'],
            'modules.*.is_delete_allowed' => ['sometimes', 'boolean'],
            'modules.*.is_listing_allowed' => ['sometimes', 'boolean'],
        ];
    }
}
