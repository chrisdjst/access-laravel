<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v2.7: tamper-evident audit log via hash chain.
 *
 * Two new nullable columns on `access_audit_log`:
 *
 *   - `entry_hash`     sha256 hex of `previous_hash || canonical(this)`
 *   - `previous_hash`  the `entry_hash` of the last entry in the same
 *                      (tenant_id, event_name) partition, or null for
 *                      the first entry.
 *
 * The hash chain is OFF by default in v2.7 — host operations that
 * don't enable `access.audit.hash_chain.enabled` see no behavioral
 * change. Existing audit rows keep their NULL hash columns; when the
 * flag flips on, fresh entries start building from that point.
 *
 * `php artisan access:audit:verify` validates the chain end-to-end
 * and exits 1 on any break.
 *
 * Idempotent via Schema::hasColumn() so re-running migrations on
 * hosts that hand-added the columns is a no-op.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('access_audit_log')) {
            return;
        }

        Schema::table('access_audit_log', function (Blueprint $table): void {
            if (! Schema::hasColumn('access_audit_log', 'entry_hash')) {
                $table->string('entry_hash', 64)->nullable()->after('payload');
            }
            if (! Schema::hasColumn('access_audit_log', 'previous_hash')) {
                $table->string('previous_hash', 64)->nullable()->after('entry_hash');
            }
        });

        // Index on entry_hash is intentionally omitted — chain lookup
        // is keyed on (tenant_id, event_name, occurred_at DESC) which
        // the existing indexes cover.
    }

    public function down(): void
    {
        if (! Schema::hasTable('access_audit_log')) {
            return;
        }

        Schema::table('access_audit_log', function (Blueprint $table): void {
            if (Schema::hasColumn('access_audit_log', 'previous_hash')) {
                $table->dropColumn('previous_hash');
            }
            if (Schema::hasColumn('access_audit_log', 'entry_hash')) {
                $table->dropColumn('entry_hash');
            }
        });
    }
};
