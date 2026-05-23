<?php

declare(strict_types=1);

namespace ModularizeRbac\Laravel\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Persistence-only Eloquent model for `access_audit_log`.
 *
 * Append-only: only `INSERT` and `SELECT` are exercised by the
 * package. No mass-assignment surface exposed; rows are constructed
 * by {@see \ModularizeRbac\Laravel\Eloquent\Mappers\AuditEntryMapper}
 * from {@see \ModularizeRbac\Core\Domain\Audit\AuditEntry} aggregates.
 */
class AuditEntry extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $table = 'access_audit_log';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'occurred_at' => 'datetime',
        ];
    }
}
