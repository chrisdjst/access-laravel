<?php

declare(strict_types=1);

use ModularizeRbac\Core\Application\Ports\LocaleResolver;

it('reads the current locale from Laravel application', function (): void {
    $this->app->setLocale('pt_BR');

    /** @var LocaleResolver $resolver */
    $resolver = $this->app->make(LocaleResolver::class);

    expect($resolver->currentLocale()->value)->toBe('pt_BR');
});

it('reads the fallback locale from app.fallback_locale', function (): void {
    config()->set('app.fallback_locale', 'en');

    /** @var LocaleResolver $resolver */
    $this->app->forgetInstance(LocaleResolver::class);
    $resolver = $this->app->make(LocaleResolver::class);

    expect($resolver->fallbackLocale()?->value)->toBe('en');
});

it('returns null when fallback_locale is unset', function (): void {
    config()->set('app.fallback_locale', null);

    $this->app->forgetInstance(LocaleResolver::class);
    /** @var LocaleResolver $resolver */
    $resolver = $this->app->make(LocaleResolver::class);

    expect($resolver->fallbackLocale())->toBeNull();
});

it('returns null when fallback_locale is whitespace-only', function (): void {
    config()->set('app.fallback_locale', '   ');

    $this->app->forgetInstance(LocaleResolver::class);
    /** @var LocaleResolver $resolver */
    $resolver = $this->app->make(LocaleResolver::class);

    expect($resolver->fallbackLocale())->toBeNull();
});
