<?php

declare(strict_types=1);

namespace ModularizeRbac\Laravel\Translations;

use ModularizeRbac\Core\Application\Ports\LanguageRepository;
use ModularizeRbac\Core\Application\Ports\TranslationRepository;
use ModularizeRbac\Core\Domain\Shared\Clock;
use ModularizeRbac\Core\Domain\Shared\IdGenerator;
use ModularizeRbac\Core\Domain\Shared\Uuid;
use ModularizeRbac\Core\Domain\Translation\LanguageCode;
use ModularizeRbac\Core\Domain\Translation\Translation as DomainTranslation;
use ModularizeRbac\Core\Exceptions\InvalidInput;

/**
 * Helper that translates the legacy HTTP `translations[]` payload
 * (`{ field: { locale: value, ... }, ... }`) into Translation
 * repository operations. Lives in the Laravel bridge because the
 * payload shape is an HTTP concern; the domain only cares about
 * single (subject, language, field) operations.
 *
 * Empty / null values delete the corresponding translation row, which
 * matches the legacy `setTranslationsBulk()` semantic.
 */
final class TranslationApplier
{
    public function __construct(
        private readonly TranslationRepository $translations,
        private readonly LanguageRepository $languages,
        private readonly IdGenerator $ids,
        private readonly Clock $clock,
    ) {
    }

    /**
     * @param  array<string, array<string, ?string>>  $payload
     */
    public function apply(string $translatableType, Uuid $translatableId, array $payload): void
    {
        foreach ($payload as $field => $byLocale) {
            if (! is_string($field) || ! is_array($byLocale)) {
                continue;
            }
            foreach ($byLocale as $locale => $value) {
                if (! is_string($locale)) {
                    continue;
                }
                $languageCode = new LanguageCode($locale);
                $language = $this->languages->findByCode($languageCode);
                if ($language === null) {
                    throw InvalidInput::of(
                        "translations.{$field}.{$locale}",
                        "Unknown language code: {$languageCode->value}",
                    );
                }

                if ($value === null || $value === '') {
                    $this->translations->deleteForSubjectField(
                        $translatableType,
                        $translatableId,
                        $language->id,
                        $field,
                    );
                    continue;
                }

                $translation = DomainTranslation::create(
                    id: $this->ids->nextUuid(),
                    translatableType: $translatableType,
                    translatableId: $translatableId,
                    languageId: $language->id,
                    field: $field,
                    value: (string) $value,
                    clock: $this->clock,
                );
                $this->translations->save($translation);
            }
        }
    }
}
