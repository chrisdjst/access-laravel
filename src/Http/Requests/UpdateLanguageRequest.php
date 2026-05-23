<?php

declare(strict_types=1);

namespace ModularizeRbac\Laravel\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLanguageRequest extends FormRequest
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
        $language = $this->route('language');
        $id = is_object($language) ? $language->id : $language;

        $codeRules = ['sometimes', 'string', 'max:10', Rule::unique('languages', 'code')->ignore($id)];

        $allowed = config('access.allowed_locales');
        if (is_array($allowed) && $allowed !== []) {
            $codeRules[] = Rule::in($allowed);
        }

        return [
            'code' => $codeRules,
            'name' => ['sometimes', 'string', 'max:50'],
            'is_default' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
