<?php

declare(strict_types=1);

namespace Modularize\Access\Laravel\Localization;

use Illuminate\Contracts\Foundation\Application;
use Modularize\Access\Application\Ports\LocaleResolver;
use Modularize\Access\Domain\Translation\LanguageCode;

/**
 * {@see LocaleResolver} adapter that reads the active locale from
 * Laravel's application container (`App::getLocale()`) and the
 * fallback from the framework's `app.fallback_locale` config.
 */
final class LaravelLocaleResolver implements LocaleResolver
{
    public function __construct(private readonly Application $app)
    {
    }

    public function currentLocale(): LanguageCode
    {
        return new LanguageCode($this->app->getLocale());
    }

    public function fallbackLocale(): ?LanguageCode
    {
        $fallback = config('app.fallback_locale');
        if (! is_string($fallback) || trim($fallback) === '') {
            return null;
        }

        return new LanguageCode($fallback);
    }
}
