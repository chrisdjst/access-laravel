<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v2.0: the package owns its own user-role assignment table so the
 * `HasAccessPermissions` trait can resolve a user's roles without
 * going through Spatie's `model_has_roles` (which is polymorphic
 * and keyed on `model_type` / `model_id`).
 *
 * The pivot is keyed on (role_id, user_id) plus an optional
 * organization_id for multi-tenant hosts that want per-tenant role
 * assignments (a user can hold different roles in different tenants).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('role_user')) {
            Schema::create('role_user', function (Blueprint $table): void {
                $table->uuid('role_id');
                $table->uuid('user_id');
                $table->uuid('organization_id')->nullable();
                $table->timestamps();

                $table->primary(['role_id', 'user_id', 'organization_id'], 'role_user_pk');
                $table->index('user_id', 'role_user_user_id_index');
                $table->index('organization_id', 'role_user_organization_id_index');

                $table->foreign('role_id')
                    ->references('id')
                    ->on('roles')
                    ->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('role_user');
    }
};
