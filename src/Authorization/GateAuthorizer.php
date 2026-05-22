<?php

declare(strict_types=1);

namespace Modularize\Access\Laravel\Authorization;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\Access\Gate;
use Modularize\Access\Application\Ports\Authorizer;
use Modularize\Access\Domain\Shared\Uuid;
use Modularize\Access\Exceptions\AuthorizationFailed;

/**
 * {@see Authorizer} adapter that delegates ability checks to
 * Laravel's `Gate` and resolves the current actor via the configured
 * guard (default: `config('access.guard_name')` → `admin`).
 *
 * Anonymous / CLI contexts: when no authenticated user can be
 * resolved, `actorId()` returns null. Whether `can()` returns true
 * for an anonymous actor depends on the Gate policies — this adapter
 * does not bypass authorization.
 */
final class GateAuthorizer implements Authorizer
{
    public function __construct(
        private readonly Gate $gate,
        private readonly AuthFactory $auth,
        private readonly string $guardName,
    ) {
    }

    public function actorId(): ?Uuid
    {
        $user = $this->resolveUser();
        if ($user === null) {
            return null;
        }
        $id = $user->getAuthIdentifier();
        if (! is_string($id)) {
            return null;
        }

        return new Uuid($id);
    }

    public function can(string $ability): bool
    {
        $user = $this->resolveUser();
        if ($user === null) {
            // Anonymous: defer to the default gate so closures with
            // an optional/nullable first parameter still match
            // (e.g. `Gate::define('x', fn () => true)`).
            return $this->gate->allows($ability);
        }

        return $this->gate->forUser($user)->allows($ability);
    }

    public function ensure(string $ability): void
    {
        if (! $this->can($ability)) {
            throw AuthorizationFailed::of($ability);
        }
    }

    private function resolveUser(): ?Authenticatable
    {
        return $this->auth->guard($this->guardName)->user();
    }
}
