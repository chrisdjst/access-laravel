<?php

declare(strict_types=1);

namespace ModularizeRbac\Laravel\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the structural shape of an incoming Language creation
 * payload. Authorization lives inside the {@see \ModularizeRbac\Core\Application\Language\CreateLanguage\CreateLanguage}
 * use-case via the {@see \ModularizeRbac\Core\Application\Ports\Authorizer}
 * port — keep this FormRequest free of permission checks.
 *
 * If `config('access.allowed_locales')` is a non-empty list, `code`
 * must match one of its entries. Empty / unset config skips the
 * check (any BCP-47-ish code is accepted).
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
        $codeRules = ['required', 'string', 'max:10', 'unique:languages,code'];

        $allowed = config('access.allowed_locales');
        if (is_array($allowed) && $allowed !== []) {
            $codeRules[] = Rule::in($allowed);
        }

        return [
            'code' => $codeRules,
            'name' => ['required', 'string', 'max:50'],
            'is_default' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
