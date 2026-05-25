<?php

declare(strict_types=1);

namespace ModularizeRbac\Laravel\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stamps every package response with an `Access-Api-Version` header
 * so SDK consumers can detect contract drift across host upgrades.
 *
 * The value comes from {@see self::API_VERSION} — a single constant
 * bumped manually when the package introduces a breaking response
 * shape change. Hosts that wrap the bridge in their own middleware
 * can read the header to fall back to legacy parsing paths if
 * needed.
 */
final class AddApiVersionHeader
{
    public const API_VERSION = '1';

    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        if (! $response->headers->has('Access-Api-Version')) {
            $response->headers->set('Access-Api-Version', self::API_VERSION);
        }

        return $response;
    }
}
