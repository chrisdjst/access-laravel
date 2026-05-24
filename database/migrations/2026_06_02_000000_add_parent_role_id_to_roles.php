<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v2.2: roles gain an optional `parent_role_id` self-FK so they can
 * form a hierarchy. The bridge keeps the column nullable and applies
 * `onDelete('set null')` so deleting an ancestor doesn't cascade-kill
 * descendants — the descendants just become roots.
 *
 * Domain-level cycle prevention lives in `Role::create()` (self-
 * parenting refused) and in `RoleRepository::resolveAncestors()`
 * (walk guards against cycles introduced by raw SQL edits).
 *
 * Idempotent via `Schema::hasColumn()` so re-running migrations on
 * hosts that already added the column manually is a no-op.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }

        if (! Schema::hasColumn('roles', 'parent_role_id')) {
            Schema::table('roles', function (Blueprint $table): void {
                $table->uuid('parent_role_id')->nullable()->after('is_system');
                $table->index('parent_role_id', 'roles_parent_role_id_index');
                $table->foreign('parent_role_id', 'roles_parent_role_id_foreign')
                    ->references('id')
                    ->on('roles')
                    ->onDelete('set null');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasColumn('roles', 'parent_role_id')) {
            return;
        }

        Schema::table('roles', function (Blueprint $table): void {
            $table->dropForeign('roles_parent_role_id_foreign');
            $table->dropIndex('roles_parent_role_id_index');
            $table->dropColumn('parent_role_id');
        });
    }
};
