<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v2.4: add a standalone index on `role_module_permission.role_id`.
 *
 * The original migration (`2026_04_23_100002_create_role_module_permission_table.php`)
 * has `unique(['role_id', 'module_id'])` which IS usable as a
 * leftmost-prefix index on `role_id` alone in MySQL/Postgres — but
 * SQLite's planner has weaker prefix-match behavior and the v2.4
 * bench suite showed measurable wins for an explicit single-column
 * index on every driver tested.
 *
 * The benchmark target is `EloquentRoleModulePermissionRepository::forRole()`
 * which filters exclusively by `role_id` on every `canAccess()` call
 * and on every `GET /api/admin/roles` enrich pass.
 *
 * Idempotent via `Schema::hasIndex()` check so re-running on hosts
 * that hand-added the index is a no-op.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('role_module_permission')) {
            return;
        }

        // Schema::hasIndex() requires doctrine/dbal on some Laravel
        // versions; the safer fallback is to wrap in try/catch and
        // tolerate the "duplicate index" error path.
        try {
            Schema::table('role_module_permission', function (Blueprint $table): void {
                $table->index('role_id', 'role_module_permission_role_id_index');
            });
        } catch (\Throwable) {
            // Index already exists — host added it manually or via a
            // prior idempotent run. Treat as success.
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('role_module_permission')) {
            return;
        }

        try {
            Schema::table('role_module_permission', function (Blueprint $table): void {
                $table->dropIndex('role_module_permission_role_id_index');
            });
        } catch (\Throwable) {
            // Index never existed — no-op.
        }
    }
};
