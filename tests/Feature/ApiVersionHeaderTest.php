<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use ModularizeRbac\Laravel\Http\Middleware\AddApiVersionHeader;

beforeEach(function (): void {
    Gate::before(fn (?\Illuminate\Contracts\Auth\Authenticatable $user, string $ability): bool => true);
});

it('stamps Access-Api-Version on every package response', function (): void {
    $response = $this->getJson('/api/admin/modules');

    $response->assertOk()
        ->assertHeader('Access-Api-Version', AddApiVersionHeader::API_VERSION);
});

it('keeps the header on POST responses too', function (): void {
    $response = $this->postJson('/api/admin/modules', ['slug' => 'events', 'name' => 'Events']);

    $response->assertCreated()
        ->assertHeader('Access-Api-Version', AddApiVersionHeader::API_VERSION);
});

it('keeps the header on validation 422 responses', function (): void {
    $response = $this->postJson('/api/admin/modules', ['slug' => '!INVALID!', 'name' => 'X']);

    $response->assertStatus(422)
        ->assertHeader('Access-Api-Version', AddApiVersionHeader::API_VERSION);
});

it('keeps the header on 404 responses', function (): void {
    $response = $this->getJson('/api/admin/modules/99999999-9999-9999-9999-999999999999');

    $response->assertStatus(404)
        ->assertHeader('Access-Api-Version', AddApiVersionHeader::API_VERSION);
});

it('does not overwrite a header explicitly set by upstream code', function (): void {
    // Sanity check via the middleware directly — if the request handler
    // already sets the header, the middleware respects it.
    $middleware = new AddApiVersionHeader();
    $request = \Illuminate\Http\Request::create('/dummy', 'GET');
    $response = $middleware->handle($request, function () {
        $r = new \Illuminate\Http\Response('hi');
        $r->headers->set('Access-Api-Version', 'override');

        return $r;
    });

    expect($response->headers->get('Access-Api-Version'))->toBe('override');
});
