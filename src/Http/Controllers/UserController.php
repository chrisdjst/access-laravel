<?php

declare(strict_types=1);

namespace ModularizeRbac\Laravel\Http\Controllers;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use ModularizeRbac\Core\Application\Module\ListUserAccessibleModules\ListUserAccessibleModules;
use ModularizeRbac\Laravel\Http\Resources\AccessibleModuleResource;

/**
 * Thin HTTP controller exposing user-centric read use-cases.
 *
 * `GET /api/admin/users/{user}/accessible-modules` — returns the
 * distinct modules a user can access through any of their roles.
 * Front-end shells call this to render the navigation tree.
 */
class UserController extends Controller
{
    public function __construct(
        private readonly ListUserAccessibleModules $listUserAccessibleModules,
    ) {
    }

    public function accessibleModules(string $user): AnonymousResourceCollection
    {
        $outputs = $this->listUserAccessibleModules->execute($user);

        return AccessibleModuleResource::collection($outputs);
    }
}
