<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only audit log for domain events crossing the access
 * boundary. Populated by {@see \ModularizeRbac\Laravel\Audit\AuditingListener}
 * whenever a domain event is dispatched through the package's
 * {@see \ModularizeRbac\Laravel\Events\LaravelEventDispatcher}.
 *
 * Retention/archival is host's responsibility — the package never
 * deletes rows from this table.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('access_audit_log')) {
            return;
        }

        Schema::create('access_audit_log', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('event_name', 150);
            $table->uuid('actor_id')->nullable();
            $table->uuid('tenant_id')->nullable();
            $table->json('payload');
            $table->timestamp('occurred_at');

            $table->index('occurred_at', 'access_audit_log_occurred_at_index');
            $table->index('event_name', 'access_audit_log_event_name_index');
            $table->index('actor_id', 'access_audit_log_actor_id_index');
            $table->index('tenant_id', 'access_audit_log_tenant_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_audit_log');
    }
};
