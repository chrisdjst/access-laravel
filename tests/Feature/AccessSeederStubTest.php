<?php

declare(strict_types=1);

it('the AccessSeeder stub is syntactically valid PHP', function (): void {
    $stub = __DIR__.'/../../database/seeders/AccessSeeder.stub';
    expect(is_file($stub))->toBeTrue();

    // `php -l` runs the parser without executing. Exit code 0 = no errors.
    $output = [];
    $code = 0;
    exec('php -l '.escapeshellarg($stub).' 2>&1', $output, $code);

    expect($code)->toBe(0)
        ->and(implode("\n", $output))->toContain('No syntax errors');
});

it('vendor:publish --tag=access-seeder lists the stub as publishable', function (): void {
    $this->artisan('vendor:publish', ['--tag' => 'access-seeder', '--force' => true])
        ->assertExitCode(0);

    expect(is_file(database_path('seeders/AccessSeeder.php')))->toBeTrue();
});
