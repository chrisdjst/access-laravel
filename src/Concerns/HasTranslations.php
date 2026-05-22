<?php

declare(strict_types=1);

namespace Modularize\Access\Laravel\Concerns;

use Modularize\Access\Laravel\Models\Language;
use Modularize\Access\Laravel\Models\Translation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\App;

/**
 * @phpstan-require-extends Model
 */
trait HasTranslations
{
    /**
     * @return MorphMany<Translation, $this>
     */
    public function translations(): MorphMany
    {
        return $this->morphMany(Translation::class, 'translatable');
    }

    /**
     * Get a translated value for a field in a specific language, falling back to the
     * default language or the model's raw attribute if no translation exists.
     */
    public function translate(string $field, ?string $languageCode = null): ?string
    {
        $code = $languageCode ?: App::getLocale();

        /** @var \Illuminate\Database\Eloquent\Collection<int, Translation> $translations */
        $translations = $this->translations;
        $translation = $translations
            ->first(fn (Translation $t) => $t->language?->code === $code && $t->field === $field);

        if ($translation) {
            return $translation->value;
        }

        $defaultCode = config('app.fallback_locale');
        if ($code !== $defaultCode) {
            $fallback = $translations
                ->first(fn (Translation $t) => $t->language?->code === $defaultCode && $t->field === $field);
            if ($fallback) {
                return $fallback->value;
            }
        }

        return $this->getAttribute($field);
    }

    /**
     * Upsert a translation for one field + language.
     */
    public function setTranslation(string $field, string $languageCode, string $value): Translation
    {
        $language = Language::query()
            ->where('code', $languageCode)
            ->firstOrFail();

        /** @var Translation $translation */
        $translation = $this->translations()->updateOrCreate(
            [
                'language_id' => $language->id,
                'field' => $field,
            ],
            ['value' => $value],
        );

        return $translation;
    }

    /**
     * Bulk set translations: ['name' => ['pt_BR' => '...', 'en' => '...'], ...].
     *
     * @param  array<string, array<string, ?string>>  $fieldsByLocale
     */
    public function setTranslationsBulk(array $fieldsByLocale): void
    {
        foreach ($fieldsByLocale as $field => $byLocale) {
            foreach ($byLocale as $locale => $value) {
                if ($value === null || $value === '') {
                    $this->translations()
                        ->where('field', $field)
                        ->whereHas('language', fn ($q) => $q->where('code', $locale))
                        ->delete();

                    continue;
                }
                $this->setTranslation($field, $locale, $value);
            }
        }
    }
}
