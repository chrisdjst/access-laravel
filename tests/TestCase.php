<?php

declare(strict_types=1);

namespace ModularizeRbac\Laravel\Tests;

use Illuminate\Database\Schema\Blueprint;
use ModularizeRbac\Laravel\AccessServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->setupAccessTables();
    }

    protected function getPackageProviders($app): array
    {
        $providers = [];
        // Spatie is opt-in in v2 — register its provider only when
        // the package is actually installed alongside the bridge.
        if (class_exists(\Spatie\Permission\PermissionServiceProvider::class)) {
            $providers[] = \Spatie\Permission\PermissionServiceProvider::class;
        }
        $providers[] = AccessServiceProvider::class;

        return $providers;
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        $app['config']->set('access.guard_name', 'web');
        // Override the production-default middleware stack so HTTP
        // tests aren't kicked by auth:sanctum / admin.auth.
        $app['config']->set('access.middleware', ['api']);
        $app['config']->set('app.fallback_locale', 'en');

        if (class_exists(\Spatie\Permission\PermissionServiceProvider::class)) {
            $app['config']->set('permission.teams', false);
        }
    }

    /**
     * Bring up the bare schema our repositories need on a fresh
     * SQLite database. We hand-roll it rather than running the
     * package migrations so the test suite stays fast and isolates
     * the schema shape that matters for integration tests.
     *
     * Mirrors the v2.0 migration set hardcoded — no config('permission.*')
     * dependency.
     */
    protected function setupAccessTables(): void
    {
        $schema = $this->app['db']->connection()->getSchemaBuilder();

        if (! $schema->hasTable('roles')) {
            $schema->create('roles', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->string('display_name')->nullable();
                $table->string('guard_name');
                $table->uuid('organization_id')->nullable();
                $table->integer('level')->default(0);
                $table->boolean('is_system')->default(false);
                $table->timestamps();
                $table->unique(['name', 'guard_name', 'organization_id']);
            });
        }

        if (! $schema->hasTable('permissions')) {
            $schema->create('permissions', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->string('guard_name');
                $table->string('module')->nullable();
                $table->timestamps();
                $table->unique(['name', 'guard_name']);
            });
        }

        if (! $schema->hasTable('role_has_permissions')) {
            $schema->create('role_has_permissions', function (Blueprint $table): void {
                $table->uuid('permission_id');
                $table->uuid('role_id');
                $table->primary(['permission_id', 'role_id']);
            });
        }

        if (! $schema->hasTable('modules')) {
            $schema->create('modules', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('slug')->unique();
                $table->string('name');
                $table->string('redirect')->nullable();
                $table->string('icon')->nullable();
                $table->uuid('root_module_id')->nullable();
                $table->integer('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->uuid('created_by')->nullable();
                $table->uuid('updated_by')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! $schema->hasTable('module_permissions')) {
            $schema->create('module_permissions', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->boolean('is_listing_allowed')->default(false);
                $table->boolean('is_reading_allowed')->default(false);
                $table->boolean('is_writing_allowed')->default(false);
                $table->boolean('is_editing_allowed')->default(false);
                $table->boolean('is_delete_allowed')->default(false);
                $table->boolean('is_active')->default(true);
                $table->uuid('created_by')->nullable();
                $table->uuid('updated_by')->nullable();
                $table->timestamps();
            });
        }

        if (! $schema->hasTable('role_module_permission')) {
            $schema->create('role_module_permission', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('role_id');
                $table->uuid('module_id');
                $table->uuid('module_permission_id');
                $table->uuid('created_by')->nullable();
                $table->uuid('updated_by')->nullable();
                $table->timestamps();
            });
        }

        if (! $schema->hasTable('languages')) {
            $schema->create('languages', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('code')->unique();
                $table->string('name');
                $table->boolean('is_default')->default(false);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! $schema->hasTable('translations')) {
            $schema->create('translations', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('translatable_type');
                $table->uuid('translatable_id');
                $table->uuid('language_id');
                $table->string('field');
                $table->text('value');
                $table->timestamps();
                $table->unique(['translatable_type', 'translatable_id', 'language_id', 'field'], 'translations_unique');
            });
        }

        if (! $schema->hasTable('module_prices')) {
            $schema->create('module_prices', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('module_id');
                $table->decimal('value', 12, 2)->default(0);
                $table->string('currency', 3)->default('BRL');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! $schema->hasTable('role_user')) {
            $schema->create('role_user', function (Blueprint $table): void {
                $table->uuid('role_id');
                $table->uuid('user_id');
                $table->uuid('organization_id')->nullable();
                $table->timestamps();
                $table->primary(['role_id', 'user_id', 'organization_id'], 'role_user_pk');
            });
        }
    }
}
