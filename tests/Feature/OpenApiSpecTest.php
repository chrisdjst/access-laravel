<?php

declare(strict_types=1);

use OpenApi\Generator;

it('access:openapi --check confirms openapi.json is in sync with the source attributes', function (): void {
    $this->artisan('access:openapi', ['--check' => true])->assertExitCode(0);
});

it('OpenApi\\Generator can build the spec from src/Http/OpenApi without errors', function (): void {
    $generator = new Generator();
    $openapi = $generator->generate([__DIR__.'/../../src/Http/OpenApi']);

    $json = $openapi->toJson();
    $decoded = json_decode($json, true);

    expect($decoded)->toBeArray()
        ->and($decoded['openapi'])->toBe('3.1.0')
        ->and($decoded['paths'])->toHaveKeys(['/modules', '/roles', '/audit'])
        ->and($decoded['components']['schemas'])->toHaveKeys(['Module', 'Role', 'Language', 'Error', 'PaginatedMeta']);
});

it('the openapi.json file shipped in the repo parses cleanly', function (): void {
    $path = __DIR__.'/../../openapi.json';
    expect(is_file($path))->toBeTrue();

    $decoded = json_decode((string) file_get_contents($path), true);
    expect($decoded)->toBeArray()
        ->and($decoded['openapi'])->toBe('3.1.0')
        ->and(isset($decoded['paths']['/modules']))->toBeTrue();
});
