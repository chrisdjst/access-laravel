<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drops the legacy users FK on RBAC audit columns. The original migrations
     * pointed created_by / updated_by at users(id), but admin-driven changes
     * insert AdminUser ids — which fail the constraint. Audit columns stay as
     * plain nullable uuids, with referential integrity handled at the app
     * layer (admin_users vs users is a polymorphic concept).
     *
     * Idempotent: only drops constraints that actually exist. This handles:
     *  - SQLite (test env): never had the FKs, skip entirely.
     *  - Fresh Postgres installs created after the create-migration patch:
     *    the FKs were never created, drop is a no-op.
     *  - Legacy Postgres installs created before the patch: drops the FKs.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'sqlite') {
            return;
        }

        $targets = [
            'modules' => ['modules_created_by_foreign', 'modules_updated_by_foreign'],
            'module_permissions' => ['module_permissions_created_by_foreign', 'module_permissions_updated_by_foreign'],
            'role_module_permission' => ['role_module_permission_created_by_foreign', 'role_module_permission_updated_by_foreign'],
        ];

        foreach ($targets as $table => $constraints) {
            foreach ($constraints as $name) {
                if ($this->postgresConstraintExists($table, $name)) {
                    DB::statement(sprintf('ALTER TABLE %s DROP CONSTRAINT %s', $table, $name));
                }
            }
        }
    }

    public function down(): void
    {
        // Not restored — pointing back at users(id) would re-introduce the bug.
    }

    protected function postgresConstraintExists(string $table, string $constraint): bool
    {
        $rows = DB::select(
            'SELECT 1 FROM pg_constraint c JOIN pg_class t ON c.conrelid = t.oid
             WHERE t.relname = ? AND c.conname = ? LIMIT 1',
            [$table, $constraint],
        );

        return ! empty($rows);
    }
};
