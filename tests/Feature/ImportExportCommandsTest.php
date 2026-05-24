<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use ModularizeRbac\Laravel\Console\ExportCommand;
use ModularizeRbac\Laravel\Models\Module;
use ModularizeRbac\Laravel\Models\Role;

beforeEach(function (): void {
    Gate::before(fn (?\Illuminate\Contracts\Auth\Authenticatable $user, string $ability): bool => true);
});

/**
 * Seed a representative payload through the HTTP API so the export
 * picks up real package state (modules, role, bindings, translations).
 */
function seedSampleState(): array
{
    $tc = test();
    $module = $tc->postJson('/api/admin/modules', [
        'slug' => 'events',
        'name' => 'Events',
        'sort_order' => 10,
    ])->json('data');

    $role = $tc->postJson('/api/admin/roles', [
        'name' => 'editor',
        'display_name' => 'Editor',
        'guard_name' => 'admin',
        'level' => 50,
    ])->json('data');

    $tc->putJson("/api/admin/roles/{$role['id']}/modules", [
        'modules' => [
            ['module_id' => $module['id'], 'is_reading_allowed' => true, 'is_writing_allowed' => true],
        ],
    ])->assertOk();

    return ['module' => $module, 'role' => $role];
}

it('access:export writes a versioned JSON payload to the given --output path', function (): void {
    seedSampleState();

    $path = sys_get_temp_dir().'/access-export-'.uniqid().'.json';
    try {
        $this->artisan('access:export', ['--output' => $path])->assertExitCode(0);

        expect(is_file($path))->toBeTrue();

        $payload = json_decode((string) file_get_contents($path), true);
        expect($payload['schema_version'])->toBe(ExportCommand::SCHEMA_VERSION)
            ->and($payload)->toHaveKeys([
                'exported_at',
                'languages',
                'modules',
                'module_permissions',
                'roles',
                'role_module_permissions',
                'translations',
            ])
            ->and($payload['modules'])->not->toBeEmpty()
            ->and($payload['roles'])->not->toBeEmpty()
            ->and($payload['role_module_permissions'])->not->toBeEmpty();
    } finally {
        if (is_file($path)) {
            unlink($path);
        }
    }
});

it('access:import with --strategy=merge re-applies a payload exported earlier', function (): void {
    seedSampleState();

    $path = sys_get_temp_dir().'/access-roundtrip-'.uniqid().'.json';
    try {
        $this->artisan('access:export', ['--output' => $path])->assertExitCode(0);

        $modulesBefore = Module::query()->count();
        $rolesBefore = Role::query()->count();

        $this->artisan('access:import', [
            'file' => $path,
            '--strategy' => 'merge',
        ])->assertExitCode(0);

        // Idempotent — re-importing doesn't grow row counts
        expect(Module::query()->count())->toBe($modulesBefore)
            ->and(Role::query()->count())->toBe($rolesBefore);
    } finally {
        if (is_file($path)) {
            unlink($path);
        }
    }
});

it('access:import with --strategy=replace --force wipes then re-inserts', function (): void {
    seedSampleState();

    $path = sys_get_temp_dir().'/access-replace-'.uniqid().'.json';
    try {
        $this->artisan('access:export', ['--output' => $path])->assertExitCode(0);

        // Add an extra module that should NOT survive a replace
        $this->postJson('/api/admin/modules', [
            'slug' => 'extra',
            'name' => 'Extra',
        ])->assertCreated();
        expect(Module::query()->count())->toBe(2);

        $this->artisan('access:import', [
            'file' => $path,
            '--strategy' => 'replace',
            '--force' => true,
        ])->assertExitCode(0);

        // Only the originally-exported module remains
        expect(Module::query()->count())->toBe(1)
            ->and(Module::query()->first()->slug)->toBe('events');
    } finally {
        if (is_file($path)) {
            unlink($path);
        }
    }
});

it('access:import rejects an unknown schema_version', function (): void {
    $path = sys_get_temp_dir().'/access-bad-version-'.uniqid().'.json';
    try {
        file_put_contents($path, json_encode([
            'schema_version' => 99,
            'languages' => [],
            'modules' => [],
            'module_permissions' => [],
            'roles' => [],
            'role_module_permissions' => [],
            'translations' => [],
        ]));

        $this->artisan('access:import', [
            'file' => $path,
            '--strategy' => 'merge',
        ])->assertExitCode(1);
    } finally {
        if (is_file($path)) {
            unlink($path);
        }
    }
});

it('access:import returns failure when the file is missing', function (): void {
    $this->artisan('access:import', [
        'file' => '/nonexistent/access-import.json',
        '--strategy' => 'merge',
    ])->assertExitCode(1);
});

it('access:import rejects an unknown --strategy', function (): void {
    $path = sys_get_temp_dir().'/access-bad-strategy-'.uniqid().'.json';
    try {
        file_put_contents($path, json_encode([
            'schema_version' => ExportCommand::SCHEMA_VERSION,
        ]));

        $this->artisan('access:import', [
            'file' => $path,
            '--strategy' => 'overwrite',
        ])->assertExitCode(1);
    } finally {
        if (is_file($path)) {
            unlink($path);
        }
    }
});
