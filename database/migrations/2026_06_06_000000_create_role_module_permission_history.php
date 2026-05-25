<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v2.8: append-only history of role-module bindings.
 *
 * Every time `role_module_permission.module_permission_id` changes
 * (or a binding is deleted), a row is inserted here capturing the
 * previous module_permission_id + the actor who triggered the change
 * + a timestamp. Lets compliance teams answer "who escalated this
 * role's permission on this module, and when?" without scanning the
 * full audit log.
 *
 * Idempotent via Schema::hasTable() so re-running migrations on
 * hosts that hand-created the table is a no-op.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('role_module_permission_history')) {
            return;
        }

        Schema::create('role_module_permission_history', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('binding_id');               // role_module_permission.id at snapshot time
            $table->uuid('role_id');                  // denormalized for fast filtering by role
            $table->uuid('module_id');                // denormalized for fast filtering by module
            $table->uuid('module_permission_id_before')->nullable();
            $table->uuid('module_permission_id_after')->nullable();
            $table->string('change_type', 32);        // 'create' | 'update' | 'delete'
            $table->uuid('actor_id')->nullable();     // host user id who triggered the change
            $table->timestamp('changed_at');
            $table->timestamps();

            $table->index(['role_id', 'changed_at']);
            $table->index(['binding_id', 'changed_at']);
            $table->index('module_id');
            $table->index('changed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_module_permission_history');
    }
};
