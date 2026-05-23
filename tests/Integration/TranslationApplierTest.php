<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use ModularizeRbac\Core\Application\Language\CreateLanguage\CreateLanguage;
use ModularizeRbac\Core\Application\Language\CreateLanguage\CreateLanguageInput;
use ModularizeRbac\Core\Application\Module\CreateModule\CreateModule;
use ModularizeRbac\Core\Application\Module\CreateModule\CreateModuleInput;
use ModularizeRbac\Core\Domain\Shared\Uuid;
use ModularizeRbac\Core\Exceptions\InvalidInput;
use ModularizeRbac\Laravel\Translations\TranslationApplier;

beforeEach(function (): void {
    Gate::before(fn (?\Illuminate\Contracts\Auth\Authenticatable $user, string $ability): bool => true);

    /** @var CreateLanguage $createLang */
    $createLang = $this->app->make(CreateLanguage::class);
    $createLang->execute(new CreateLanguageInput('pt_BR', 'Português', isDefault: true));
    $createLang->execute(new CreateLanguageInput('en', 'English'));

    /** @var CreateModule $createMod */
    $createMod = $this->app->make(CreateModule::class);
    $this->moduleId = $createMod->execute(new CreateModuleInput('events', 'Events', null, null, null))->id;
});

it('persists translation rows for each field × locale pair', function (): void {
    /** @var TranslationApplier $applier */
    $applier = $this->app->make(TranslationApplier::class);

    $applier->apply('module', new Uuid($this->moduleId), [
        'name' => [
            'pt_BR' => 'Eventos',
            'en' => 'Events',
        ],
    ]);

    $rows = DB::table('translations')
        ->where('translatable_type', 'module')
        ->where('translatable_id', $this->moduleId)
        ->get();

    expect($rows)->toHaveCount(2);
});

it('deletes a translation when the value is an empty string', function (): void {
    /** @var TranslationApplier $applier */
    $applier = $this->app->make(TranslationApplier::class);

    $applier->apply('module', new Uuid($this->moduleId), [
        'name' => ['pt_BR' => 'Eventos', 'en' => 'Events'],
    ]);

    $applier->apply('module', new Uuid($this->moduleId), [
        'name' => ['pt_BR' => ''],
    ]);

    $remaining = DB::table('translations')
        ->where('translatable_type', 'module')
        ->where('translatable_id', $this->moduleId)
        ->get();

    expect($remaining)->toHaveCount(1); // the `en` row survived
});

it('deletes a translation when the value is null', function (): void {
    /** @var TranslationApplier $applier */
    $applier = $this->app->make(TranslationApplier::class);

    $applier->apply('module', new Uuid($this->moduleId), [
        'name' => ['pt_BR' => 'Eventos'],
    ]);

    $applier->apply('module', new Uuid($this->moduleId), [
        'name' => ['pt_BR' => null],
    ]);

    expect(DB::table('translations')->count())->toBe(0);
});

it('throws InvalidInput for an unknown locale', function (): void {
    /** @var TranslationApplier $applier */
    $applier = $this->app->make(TranslationApplier::class);

    $applier->apply('module', new Uuid($this->moduleId), [
        'name' => ['ja' => 'イベント'],
    ]);
})->throws(InvalidInput::class, 'Unknown language code: ja');

it('silently skips entries whose field key is not a string', function (): void {
    /** @var TranslationApplier $applier */
    $applier = $this->app->make(TranslationApplier::class);

    $applier->apply('module', new Uuid($this->moduleId), [
        0 => ['pt_BR' => 'Eventos'],
    ]);

    expect(DB::table('translations')->count())->toBe(0);
});

it('silently skips entries whose byLocale is not an array', function (): void {
    /** @var TranslationApplier $applier */
    $applier = $this->app->make(TranslationApplier::class);

    /** @phpstan-ignore-next-line */
    $applier->apply('module', new Uuid($this->moduleId), [
        'name' => 'just a string',
    ]);

    expect(DB::table('translations')->count())->toBe(0);
});
