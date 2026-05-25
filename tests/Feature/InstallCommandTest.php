<?php

declare(strict_types=1);

beforeEach(function (): void {
    // Clean up any artefacts a previous test run may have published
    $files = [
        config_path('access.php'),
        database_path('seeders/AccessSeeder.php'),
    ];
    foreach ($files as $f) {
        if (is_file($f)) {
            @unlink($f);
        }
    }
});

it('access:install publishes the config + runs migrations', function (): void {
    $this->artisan('access:install', ['--no-migrate' => true])->assertExitCode(0);

    expect(is_file(config_path('access.php')))->toBeTrue();
});

it('--with-seeder also publishes the seeder stub', function (): void {
    $this->artisan('access:install', ['--no-migrate' => true, '--with-seeder' => true])->assertExitCode(0);

    expect(is_file(database_path('seeders/AccessSeeder.php')))->toBeTrue();
});

it('--no-config skips publishing the config file', function (): void {
    $this->artisan('access:install', ['--no-migrate' => true, '--no-config' => true])->assertExitCode(0);

    expect(is_file(config_path('access.php')))->toBeFalse();
});

it('prints the User-trait wiring snippet', function (): void {
    $this->artisan('access:install', ['--no-migrate' => true])
        ->expectsOutputToContain('HasAccessPermissions')
        ->assertExitCode(0);
});
