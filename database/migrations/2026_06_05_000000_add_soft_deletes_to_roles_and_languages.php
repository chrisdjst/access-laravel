<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v2.8: add `deleted_at` columns to `roles` and `languages` so the
 * v1.9 core soft-delete semantics flow through to persistence.
 *
 * Idempotent via `Schema::hasColumn()` — re-running on hosts that
 * hand-added the columns is a no-op.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['roles', 'languages'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            if (Schema::hasColumn($table, 'deleted_at')) {
                continue;
            }
            Schema::table($table, function (Blueprint $t): void {
                $t->softDeletes();
            });
        }
    }

    public function down(): void
    {
        foreach (['roles', 'languages'] as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'deleted_at')) {
                continue;
            }
            Schema::table($table, function (Blueprint $t): void {
                $t->dropSoftDeletes();
            });
        }
    }
};
