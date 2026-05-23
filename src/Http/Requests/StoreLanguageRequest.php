<?php

declare(strict_types=1);

namespace ModularizeRbac\Laravel\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the structural shape of an incoming Language creation
 * payload. Authorization lives inside the {@see \ModularizeRbac\Core\Application\Language\CreateLanguage\CreateLanguage}
 * use-case via the {@see \ModularizeRbac\Core\Application\Ports\Authorizer}
 * port — keep this FormRequest free of permission checks.
 */
class StoreLanguageRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:10', 'unique:languages,code'],
            'name' => ['required', 'string', 'max:50'],
            'is_default' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
