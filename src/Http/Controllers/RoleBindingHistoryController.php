<?php

declare(strict_types=1);

namespace ModularizeRbac\Laravel\Http\Controllers;

use DateTimeInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use ModularizeRbac\Core\Application\Ports\Authorizer;

/**
 * Read-only endpoint exposing the append-only history captured by
 * {@see \ModularizeRbac\Laravel\Eloquent\Repositories\EloquentRoleModulePermissionRepository}.
 *
 * Use cases:
 *
 *  - Compliance review: "who escalated this role's permission on
 *    the billing module, and when?"
 *  - Permission-creep audits during quarterly reviews.
 *
 * Authorization: `admin.roles.view` — same ability that gates the
 * underlying role data. History rows surface the same identifiers
 * already visible via the standard role endpoints, so the access
 * level matches.
 */
class RoleBindingHistoryController extends Controller
{
    public function __construct(private readonly Authorizer $authorizer)
    {
    }

    public function index(Request $request, string $role): JsonResponse
    {
        $this->authorizer->ensure('admin.roles.view');

        $limit = max(1, min(1000, (int) $request->query('limit', '50')));
        $offset = max(0, (int) $request->query('offset', '0'));

        $base = DB::table('role_module_permission_history')->where('role_id', $role);

        if (is_string($since = $request->query('since')) && $since !== '') {
            $base->where('changed_at', '>=', $since);
        }
        if (is_string($moduleId = $request->query('module_id')) && $moduleId !== '') {
            $base->where('module_id', $moduleId);
        }

        $total = (int) (clone $base)->count();

        $rows = $base
            ->orderByDesc('changed_at')
            ->orderByDesc('id')
            ->offset($offset)
            ->limit($limit)
            ->get();

        $data = $rows->map(static function ($row): array {
            $changedAt = $row->changed_at instanceof DateTimeInterface
                ? $row->changed_at->format(DateTimeInterface::ATOM)
                : (string) $row->changed_at;

            return [
                'id' => (string) $row->id,
                'binding_id' => (string) $row->binding_id,
                'role_id' => (string) $row->role_id,
                'module_id' => (string) $row->module_id,
                'module_permission_id_before' => $row->module_permission_id_before !== null ? (string) $row->module_permission_id_before : null,
                'module_permission_id_after' => $row->module_permission_id_after !== null ? (string) $row->module_permission_id_after : null,
                'change_type' => (string) $row->change_type,
                'actor_id' => $row->actor_id !== null ? (string) $row->actor_id : null,
                'changed_at' => $changedAt,
            ];
        })->all();

        return response()->json([
            'data' => $data,
            'meta' => [
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
            ],
        ]);
    }
}
