<?php

declare(strict_types=1);

namespace ModularizeRbac\Laravel\Benchmarks;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application as IlluminateApplication;
use ModularizeRbac\Laravel\AccessServiceProvider;
use Orchestra\Testbench\Foundation\Application as TestbenchApplication;
use Orchestra\Testbench\Foundation\Config;

/**
 * Slim Testbench-style harness for PHPBench subjects.
 *
 * Orchestra's PHPUnit TestCase is coupled to the PHPUnit lifecycle, so
 * we can't extend it directly from a phpbench subject. Instead this
 * helper boots an `Illuminate\Foundation\Application` via Testbench's
 * factory + sets up the same schema `tests/TestCase::setupAccessTables()`
 * uses, but exposes the result as a plain `bootApp()` method that
 * benchmark subjects call from `setUp()`.
 *
 * The schema mirrors the test harness intentionally: benchmarks should
 * measure code paths over a schema identical to what tests verify.
 */
abstract class BenchTestCase
{
    protected ?IlluminateApplication $app = null;

    /**
     * Boot a fresh in-memory app + schema. Idempotent if called
     * multiple times — only the first call wires the container.
     */
    protected function bootApp(): void
    {
        if ($this->app !== null) {
            return;
        }
        $app = $this->createApplication();
        $this->setupAccessTables($app);
        $this->app = $app;
    }

    protected function createApplication(): IlluminateApplication
    {
        $config = new Config([
            'providers' => [
                AccessServiceProvider::class,
            ],
            'env' => [
                'APP_ENV' => 'testing',
                'DB_CONNECTION' => 'testing',
            ],
        ]);

        $app = TestbenchApplication::createFromConfig($config, null, [
            'load_environment_variables' => false,
        ]);

        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        $app['config']->set('access.guard_name', 'web');
        $app['config']->set('access.middleware', ['api']);
        $app['config']->set('cache.default', 'array');

        return $app;
    }

    protected function setupAccessTables(Application $app): void
    {
        $schema = $app['db']->connection()->getSchemaBuilder();

        $schema->create('users', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->string('name')->nullable();
            $t->timestamps();
        });

        $schema->create('roles', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->string('name');
            $t->string('display_name')->nullable();
            $t->string('guard_name');
            $t->uuid('organization_id')->nullable();
            $t->integer('level')->default(0);
            $t->boolean('is_system')->default(false);
            $t->uuid('parent_role_id')->nullable();
            $t->timestamps();
            $t->unique(['name', 'guard_name', 'organization_id']);
            $t->index('parent_role_id');
        });

        $schema->create('permissions', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->string('name');
            $t->string('guard_name');
            $t->string('module')->nullable();
            $t->timestamps();
            $t->unique(['name', 'guard_name']);
        });

        $schema->create('role_has_permissions', function (Blueprint $t): void {
            $t->uuid('permission_id');
            $t->uuid('role_id');
            $t->primary(['permission_id', 'role_id']);
        });

        $schema->create('modules', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->string('slug')->unique();
            $t->string('name');
            $t->string('redirect')->nullable();
            $t->string('icon')->nullable();
            $t->uuid('root_module_id')->nullable();
            $t->integer('sort_order')->default(0);
            $t->boolean('is_active')->default(true);
            $t->uuid('created_by')->nullable();
            $t->uuid('updated_by')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->index(['root_module_id', 'sort_order']);
        });

        $schema->create('module_permissions', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->boolean('is_listing_allowed')->default(false);
            $t->boolean('is_reading_allowed')->default(false);
            $t->boolean('is_writing_allowed')->default(false);
            $t->boolean('is_editing_allowed')->default(false);
            $t->boolean('is_delete_allowed')->default(false);
            $t->boolean('is_active')->default(true);
            $t->uuid('created_by')->nullable();
            $t->uuid('updated_by')->nullable();
            $t->timestamps();
        });

        $schema->create('role_module_permission', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->uuid('role_id');
            $t->uuid('module_id');
            $t->uuid('module_permission_id');
            $t->uuid('created_by')->nullable();
            $t->uuid('updated_by')->nullable();
            $t->timestamps();
            $t->unique(['role_id', 'module_id']);
            $t->index('role_id', 'role_module_permission_role_id_index');
            $t->index('module_id');
        });

        $schema->create('role_user', function (Blueprint $t): void {
            $t->uuid('role_id');
            $t->uuid('user_id');
            $t->uuid('organization_id')->nullable();
            $t->timestamps();
            $t->primary(['role_id', 'user_id', 'organization_id'], 'role_user_pk');
            $t->index('user_id');
        });

        $schema->create('languages', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->string('code')->unique();
            $t->string('name');
            $t->boolean('is_default')->default(false);
            $t->timestamps();
        });

        $schema->create('translations', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->uuid('language_id');
            $t->string('subject_type');
            $t->uuid('subject_id');
            $t->string('field');
            $t->text('value')->nullable();
            $t->timestamps();
            $t->unique(['language_id', 'subject_type', 'subject_id', 'field'], 'translations_natural_key');
        });

        $schema->create('access_audit_log', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->string('event_type');
            $t->uuid('actor_id')->nullable();
            $t->uuid('tenant_id')->nullable();
            $t->json('payload')->nullable();
            $t->timestamp('occurred_at');
            $t->timestamps();
            $t->index('event_type');
            $t->index('occurred_at');
        });

        $schema->create('module_prices', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->uuid('module_id');
            $t->decimal('value', 10, 2);
            $t->string('currency', 3);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
    }
}
