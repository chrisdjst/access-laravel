<?php

declare(strict_types=1);

namespace ModularizeRbac\Laravel\Console;

use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Console\Command;
use ModularizeRbac\Laravel\Models\Language;
use ModularizeRbac\Laravel\Models\Module;
use ModularizeRbac\Laravel\Models\ModulePermission;
use ModularizeRbac\Laravel\Models\Role;
use ModularizeRbac\Laravel\Models\RoleModulePermission;
use ModularizeRbac\Laravel\Models\Translation;

/**
 * Dumps the package state (modules, roles, bindings, languages,
 * translations) to a JSON payload — either stdout or a file.
 *
 * Pairs with {@see ImportCommand} for env-to-env replication of an
 * RBAC catalog. The payload carries a `schema_version` so importers
 * on older codebases can refuse unknown shapes.
 */
class ExportCommand extends Command
{
    public const SCHEMA_VERSION = 1;

    protected $signature = 'access:export {--output= : Path to write JSON to. Omit to print to stdout.}';

    protected $description = 'Export the package state (modules/roles/bindings/languages/translations) as JSON.';

    public function handle(): int
    {
        $payload = [
            'schema_version' => self::SCHEMA_VERSION,
            'exported_at' => (new DateTimeImmutable())->format(DateTimeInterface::ATOM),
            'languages' => Language::query()->orderBy('code')->get()->map(fn (Language $l) => [
                'id' => (string) $l->id,
                'code' => (string) $l->code,
                'name' => (string) $l->name,
                'is_default' => (bool) $l->is_default,
            ])->all(),
            'modules' => Module::withTrashed()->orderBy('slug')->get()->map(fn (Module $m) => [
                'id' => (string) $m->id,
                'slug' => (string) $m->slug,
                'name' => (string) $m->name,
                'redirect' => $m->redirect,
                'icon' => $m->icon,
                'root_module_id' => $m->root_module_id,
                'sort_order' => (int) $m->sort_order,
                'is_active' => (bool) $m->is_active,
                'deleted_at' => $m->deleted_at?->toIso8601String(),
            ])->all(),
            'module_permissions' => ModulePermission::query()->orderBy('id')->get()->map(fn (ModulePermission $p) => [
                'id' => (string) $p->id,
                'is_listing_allowed' => (bool) $p->is_listing_allowed,
                'is_reading_allowed' => (bool) $p->is_reading_allowed,
                'is_writing_allowed' => (bool) $p->is_writing_allowed,
                'is_editing_allowed' => (bool) $p->is_editing_allowed,
                'is_delete_allowed' => (bool) $p->is_delete_allowed,
            ])->all(),
            'roles' => Role::query()->orderBy('name')->get()->map(fn (Role $r) => [
                'id' => (string) $r->id,
                'name' => (string) $r->name,
                'display_name' => $r->display_name,
                'guard_name' => (string) $r->guard_name,
                'organization_id' => $r->organization_id,
                'level' => (int) $r->level,
                'is_system' => (bool) $r->is_system,
                'parent_role_id' => $r->parent_role_id,
            ])->all(),
            'role_module_permissions' => RoleModulePermission::query()->orderBy('id')->get()->map(fn (RoleModulePermission $b) => [
                'id' => (string) $b->id,
                'role_id' => (string) $b->role_id,
                'module_id' => (string) $b->module_id,
                'module_permission_id' => (string) $b->module_permission_id,
            ])->all(),
            'translations' => Translation::query()->orderBy('id')->get()->map(fn (Translation $t) => [
                'id' => (string) $t->id,
                'language_id' => (string) $t->language_id,
                'subject_type' => (string) $t->subject_type,
                'subject_id' => (string) $t->subject_id,
                'field' => (string) $t->field,
                'value' => $t->value,
            ])->all(),
        ];

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            $this->error('Failed to encode payload as JSON.');

            return self::FAILURE;
        }

        $output = (string) $this->option('output');
        if ($output !== '') {
            file_put_contents($output, $json);
            $this->info("Exported access state to: {$output}");

            return self::SUCCESS;
        }

        $this->line($json);

        return self::SUCCESS;
    }
}
