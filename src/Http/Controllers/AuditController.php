<?php

declare(strict_types=1);

namespace ModularizeRbac\Laravel\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use ModularizeRbac\Core\Application\Audit\ListAuditEntries\ListAuditEntries;
use ModularizeRbac\Core\Application\Audit\ListAuditEntries\ListAuditEntriesInput;
use ModularizeRbac\Laravel\Http\Resources\AuditEntryResource;

/**
 * Thin HTTP controller — delegates to the
 * {@see ListAuditEntries} use-case.
 *
 * Filters arrive as query string parameters:
 *   ?event=module.created
 *   ?actor_id={uuid}
 *   ?tenant_id={uuid}
 *   ?since=2026-01-01
 *   ?until=2026-12-31
 *   ?limit=50&offset=0
 *
 * Authorization: `admin.audit.view` (enforced by the use-case).
 */
class AuditController extends Controller
{
    public function __construct(private readonly ListAuditEntries $listAuditEntries)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $output = $this->listAuditEntries->execute(new ListAuditEntriesInput(
            event: $request->query('event'),
            actorId: $request->query('actor_id'),
            tenantId: $request->query('tenant_id'),
            since: $request->query('since'),
            until: $request->query('until'),
            limit: (int) $request->query('limit', 100),
            offset: (int) $request->query('offset', 0),
        ));

        return AuditEntryResource::collection($output->entries)->additional([
            'meta' => [
                'total' => $output->total,
                'limit' => $output->limit,
                'offset' => $output->offset,
            ],
        ])->response();
    }
}
