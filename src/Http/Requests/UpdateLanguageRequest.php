<?php

declare(strict_types=1);

namespace Modularize\Access\Laravel\Http\Requests;

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

        return [
            'code' => ['sometimes', 'string', 'max:10', Rule::unique('languages', 'code')->ignore($id)],
            'name' => ['sometimes', 'string', 'max:50'],
            'is_default' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
