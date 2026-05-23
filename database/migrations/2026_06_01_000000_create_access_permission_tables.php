<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v2.0 replacement for `2026_03_11_003000_create_permission_tables.php`,
 * which depended on `config('permission.*')` and thus required
 * `spatie/laravel-permission` to be installed.
 *
 * This migration creates the same schema (column names match Spatie's
 * defaults) but is self-contained: no config lookups, no Spatie
 * dependency. Spatie sync (when enabled) operates on these tables
 * because Spatie's default `config('permission.table_names')` aligns
 * with the names below.
 *
 * Idempotent via `Schema::hasTable()` guards so hosts upgrading from
 * v1 (where the old migration already ran) don't re-create tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permissions')) {
            Schema::create('permissions', static function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->string('guard_name');
                $table->string('module', 100)->nullable();
                $table->timestamps();
                $table->unique(['name', 'guard_name']);
            });
        }

        if (! Schema::hasTable('roles')) {
            Schema::create('roles', static function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('organization_id')->nullable();
                $table->index('organization_id', 'roles_organization_id_index');
                $table->string('name');
                $table->string('display_name')->nullable();
                $table->string('guard_name');
                $table->integer('level')->default(0);
                $table->boolean('is_system')->default(false);
                $table->timestamps();
                $table->unique(['organization_id', 'name', 'guard_name']);
            });
        }

        if (! Schema::hasTable('model_has_permissions')) {
            Schema::create('model_has_permissions', static function (Blueprint $table): void {
                $table->uuid('permission_id');
                $table->string('model_type');
                $table->uuid('model_id');
                $table->uuid('organization_id')->nullable();
                $table->index(['model_id', 'model_type'], 'model_has_permissions_model_id_model_type_index');
                $table->index('organization_id', 'model_has_permissions_organization_id_index');
                $table->foreign('permission_id')
                    ->references('id')
                    ->on('permissions')
                    ->onDelete('cascade');
                $table->primary(
                    ['organization_id', 'permission_id', 'model_id', 'model_type'],
                    'model_has_permissions_pk',
                );
            });
        }

        if (! Schema::hasTable('model_has_roles')) {
            Schema::create('model_has_roles', static function (Blueprint $table): void {
                $table->uuid('role_id');
                $table->string('model_type');
                $table->uuid('model_id');
                $table->uuid('organization_id')->nullable();
                $table->index(['model_id', 'model_type'], 'model_has_roles_model_id_model_type_index');
                $table->index('organization_id', 'model_has_roles_organization_id_index');
                $table->foreign('role_id')
                    ->references('id')
                    ->on('roles')
                    ->onDelete('cascade');
                $table->primary(
                    ['organization_id', 'role_id', 'model_id', 'model_type'],
                    'model_has_roles_pk',
                );
            });
        }

        if (! Schema::hasTable('role_has_permissions')) {
            Schema::create('role_has_permissions', static function (Blueprint $table): void {
                $table->uuid('permission_id');
                $table->uuid('role_id');
                $table->foreign('permission_id')
                    ->references('id')
                    ->on('permissions')
                    ->onDelete('cascade');
                $table->foreign('role_id')
                    ->references('id')
                    ->on('roles')
                    ->onDelete('cascade');
                $table->primary(['permission_id', 'role_id'], 'role_has_permissions_pk');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('role_has_permissions');
        Schema::dropIfExists('model_has_roles');
        Schema::dropIfExists('model_has_permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');
    }
};
