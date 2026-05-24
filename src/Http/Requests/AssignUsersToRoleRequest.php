<?php

declare(strict_types=1);

namespace ModularizeRbac\Laravel\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignUsersToRoleRequest extends FormRequest
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
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['required', 'uuid'],
            'organization_id' => ['sometimes', 'nullable', 'uuid'],
        ];
    }
}
