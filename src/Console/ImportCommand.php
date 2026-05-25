<?php

declare(strict_types=1);

namespace ModularizeRbac\Laravel\Console;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use ModularizeRbac\Laravel\Models\Language;
use ModularizeRbac\Laravel\Models\Module;
use ModularizeRbac\Laravel\Models\ModulePermission;
use ModularizeRbac\Laravel\Models\Role;
use ModularizeRbac\Laravel\Models\RoleModulePermission;
use ModularizeRbac\Laravel\Models\Translation;

/**
 * Loads a payload produced by {@see ExportCommand} into the current
 * database. Two strategies:
 *
 *   merge   — upsert rows by id. Existing rows are updated, missing
 *             rows inserted. Translations are upserted on the natural
 *             key (language_id, subject_type, subject_id, field).
 *
 *   replace — wipe every package-owned table first, then insert. Use
 *             with care — destructive. `--force` skips the confirm
 *             prompt for unattended invocations.
 */
class ImportCommand extends Command
{
    protected $signature = 'access:import {file : Path to a JSON file produced by access:export.}
        {--strategy=merge : merge or replace}
        {--force : Skip the confirmation prompt for --strategy=replace}';

    protected $description = 'Import a JSON payload produced by access:export.';

    public function handle(): int
    {
        $file = (string) $this->argument('file');
        if (! is_file($file)) {
            $this->error("File not found: {$file}");

            return self::FAILURE;
        }
        $raw = file_get_contents($file);
        if ($raw === false) {
            $this->error("Failed to read: {$file}");

            return self::FAILURE;
        }
        $payload = json_decode($raw, true);
        if (! is_array($payload)) {
            $this->error('Payload is not a valid JSON object.');

            return self::FAILURE;
        }

        $version = $payload['schema_version'] ?? null;
        if ($version !== ExportCommand::SCHEMA_VERSION) {
            $this->error(sprintf(
                'Unsupported schema_version: %s (expected %d).',
                var_export($version, true),
                ExportCommand::SCHEMA_VERSION,
            ));

            return self::FAILURE;
        }

        $strategy = (string) $this->option('strategy');
        if (! in_array($strategy, ['merge', 'replace'], true)) {
            $this->error("Unknown strategy: {$strategy} (expected 'merge' or 'replace').");

            return self::FAILURE;
        }

        if ($strategy === 'replace' && ! (bool) $this->option('force')) {
            if (! $this->confirm('--strategy=replace will WIPE every package-owned table before insert. Continue?', false)) {
                $this->warn('Aborted.');

                return self::FAILURE;
            }
        }

        // Models have $fillable that excludes 'id'; unguard for the
        // import flow so we can preserve ids verbatim.
        Model::unguarded(function () use ($payload, $strategy): void {
            DB::transaction(function () use ($payload, $strategy): void {
                if ($strategy === 'replace') {
                    Translation::query()->delete();
                    RoleModulePermission::query()->delete();
                    // Force-delete to bypass SoftDeletes scopes — replace mode wipes everything.
                    Role::withTrashed()->forceDelete();
                    ModulePermission::query()->delete();
                    Module::query()->withTrashed()->forceDelete();
                    Language::withTrashed()->forceDelete();
                }

                $this->importLanguages($payload['languages'] ?? []);
                $this->importModules($payload['modules'] ?? []);
                $this->importModulePermissions($payload['module_permissions'] ?? []);
                $this->importRoles($payload['roles'] ?? []);
                $this->importBindings($payload['role_module_permissions'] ?? []);
                $this->importTranslations($payload['translations'] ?? []);
            });
        });

        $this->info("Imported with strategy={$strategy}.");

        return self::SUCCESS;
    }

    /** @param list<array<string, mixed>> $rows */
    private function importLanguages(array $rows): void
    {
        foreach ($rows as $row) {
            Language::query()->updateOrCreate(
                ['id' => $row['id']],
                [
                    'code' => $row['code'],
                    'name' => $row['name'],
                    'is_default' => (bool) ($row['is_default'] ?? false),
                ],
            );
        }
    }

    /** @param list<array<string, mixed>> $rows */
    private function importModules(array $rows): void
    {
        foreach ($rows as $row) {
            $values = [
                'slug' => $row['slug'],
                'name' => $row['name'],
                'redirect' => $row['redirect'] ?? null,
                'icon' => $row['icon'] ?? null,
                'root_module_id' => $row['root_module_id'] ?? null,
                'sort_order' => (int) ($row['sort_order'] ?? 0),
                'is_active' => (bool) ($row['is_active'] ?? true),
            ];
            // Carry over soft-deleted state when present
            if (! empty($row['deleted_at'])) {
                $values['deleted_at'] = $row['deleted_at'];
            }
            Module::withTrashed()->updateOrCreate(['id' => $row['id']], $values);
        }
    }

    /** @param list<array<string, mixed>> $rows */
    private function importModulePermissions(array $rows): void
    {
        foreach ($rows as $row) {
            ModulePermission::query()->updateOrCreate(
                ['id' => $row['id']],
                [
                    'is_listing_allowed' => (bool) ($row['is_listing_allowed'] ?? false),
                    'is_reading_allowed' => (bool) ($row['is_reading_allowed'] ?? false),
                    'is_writing_allowed' => (bool) ($row['is_writing_allowed'] ?? false),
                    'is_editing_allowed' => (bool) ($row['is_editing_allowed'] ?? false),
                    'is_delete_allowed' => (bool) ($row['is_delete_allowed'] ?? false),
                ],
            );
        }
    }

    /** @param list<array<string, mixed>> $rows */
    private function importRoles(array $rows): void
    {
        foreach ($rows as $row) {
            Role::query()->updateOrCreate(
                ['id' => $row['id']],
                [
                    'name' => $row['name'],
                    'display_name' => $row['display_name'] ?? null,
                    'guard_name' => $row['guard_name'],
                    'organization_id' => $row['organization_id'] ?? null,
                    'level' => (int) ($row['level'] ?? 0),
                    'is_system' => (bool) ($row['is_system'] ?? false),
                    'parent_role_id' => $row['parent_role_id'] ?? null,
                ],
            );
        }
    }

    /** @param list<array<string, mixed>> $rows */
    private function importBindings(array $rows): void
    {
        foreach ($rows as $row) {
            RoleModulePermission::query()->updateOrCreate(
                ['id' => $row['id']],
                [
                    'role_id' => $row['role_id'],
                    'module_id' => $row['module_id'],
                    'module_permission_id' => $row['module_permission_id'],
                ],
            );
        }
    }

    /** @param list<array<string, mixed>> $rows */
    private function importTranslations(array $rows): void
    {
        foreach ($rows as $row) {
            Translation::query()->updateOrCreate(
                [
                    'language_id' => $row['language_id'],
                    'subject_type' => $row['subject_type'],
                    'subject_id' => $row['subject_id'],
                    'field' => $row['field'],
                ],
                [
                    'id' => $row['id'],
                    'value' => $row['value'],
                ],
            );
        }
    }
}
