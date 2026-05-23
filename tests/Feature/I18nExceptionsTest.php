<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;

beforeEach(function (): void {
    Gate::before(fn (?\Illuminate\Contracts\Auth\Authenticatable $user, string $ability): bool => true);
});

function triggerUseCaseInvalidInput(): \Illuminate\Testing\TestResponse
{
    // The DeleteLanguage use-case throws InvalidInput when asked to
    // delete the default language. This is a use-case-level rule
    // that the FormRequest can't catch via `unique`/`exists` rules,
    // so the package's renderer handles it (vs Laravel's default
    // 422 shape).
    $lang = test()->postJson('/api/admin/languages', [
        'code' => 'pt_BR',
        'name' => 'Português',
        'is_default' => true,
    ])->json('data');

    return test()->deleteJson("/api/admin/languages/{$lang['id']}");
}

it('returns the English error_type by default', function (): void {
    $response = triggerUseCaseInvalidInput();

    $response->assertStatus(422)
        ->assertJsonPath('error_type', 'Invalid input');
});

it('returns the Portuguese error_type when locale is pt_BR', function (): void {
    $this->app->setLocale('pt_BR');

    $response = triggerUseCaseInvalidInput();

    $response->assertStatus(422)
        ->assertJsonPath('error_type', 'Entrada inválida');
});

it('returns localized error_type on NotFound (404)', function (): void {
    $this->app->setLocale('pt_BR');

    $response = $this->getJson('/api/admin/modules/11111111-1111-1111-1111-111111111111');

    $response->assertStatus(404)
        ->assertJsonPath('error_type', 'Não encontrado');
});

it('returns localized error_type on AuthorizationFailed (403)', function (): void {
    // Skip this file's beforeEach blanket-allow Gate::before for
    // `admin.modules.view`. Gate::before callbacks run in
    // registration order; the blanket one fires first and would
    // short-circuit with `true`. Use the Authorizer port directly
    // through a fresh container binding instead.
    $this->app->bind(
        \ModularizeRbac\Core\Application\Ports\Authorizer::class,
        function () {
            return new class implements \ModularizeRbac\Core\Application\Ports\Authorizer {
                public function actorId(): ?\ModularizeRbac\Core\Domain\Shared\Uuid
                {
                    return null;
                }

                public function can(string $ability): bool
                {
                    return false;
                }

                public function ensure(string $ability): void
                {
                    throw \ModularizeRbac\Core\Exceptions\AuthorizationFailed::of($ability);
                }
            };
        },
    );

    $this->app->setLocale('pt_BR');

    $response = $this->getJson('/api/admin/modules');

    $response->assertStatus(403)
        ->assertJsonPath('error_type', 'Acesso negado');
});

it('preserves the original message string regardless of locale', function (): void {
    $this->app->setLocale('pt_BR');

    $response = triggerUseCaseInvalidInput();

    // The detailed message stays in whatever language the use-case
    // emitted (English in v2.1). Only the `error_type` headline is
    // localized — keeps backwards compat with clients reading
    // `message` as a free-form string.
    $response->assertJsonPath('message', 'Cannot delete the default language.');
});
