<?php

declare(strict_types=1);

namespace ModularizeRbac\Laravel\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkCreateModulesRequest extends FormRequest
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
            'modules' => ['required', 'array', 'min:1'],
            'modules.*' => ['array'],
            'modules.*.slug' => ['required', 'string', 'max:120'],
            'modules.*.name' => ['required', 'string', 'max:120'],
            'modules.*.redirect' => ['sometimes', 'nullable', 'string', 'max:255'],
            'modules.*.icon' => ['sometimes', 'nullable', 'string', 'max:64'],
            'modules.*.root_module_id' => ['sometimes', 'nullable', 'uuid'],
            'modules.*.sort_order' => ['sometimes', 'integer', 'min:0'],
            'modules.*.is_active' => ['sometimes', 'boolean'],
        ];
    }
}
