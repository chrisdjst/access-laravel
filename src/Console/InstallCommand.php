<?php

declare(strict_types=1);

namespace ModularizeRbac\Laravel\Console;

use Illuminate\Console\Command;

/**
 * One-shot scaffold command that does for `modularize-rbac/laravel`
 * what `sanctum:install` does for Sanctum: get a fresh host from
 * `composer require ...` to "first canAccess() works" without manual
 * file edits.
 *
 * Steps (each can be skipped via the matching --no-* flag):
 *   1. Publish config (`access-config` tag)
 *   2. Publish lang files (`access-lang` tag) — opt-in via flag
 *   3. Publish the example seeder (`access-seeder` tag) — opt-in via flag
 *   4. Run migrations
 *   5. Print the User-trait wiring snippet (no automatic file edit
 *      to avoid surprising hosts)
 */
class InstallCommand extends Command
{
    protected $signature = 'access:install
        {--no-config : Skip publishing config/access.php}
        {--no-migrate : Skip running migrations}
        {--with-lang : Also publish lang/en/exceptions.php and pt_BR/exceptions.php}
        {--with-seeder : Also publish the AccessSeeder.stub example}';

    protected $description = 'Scaffold the package in a fresh host: publish config, migrate, and print the User-trait snippet.';

    public function handle(): int
    {
        $this->components->info('Installing modularize-rbac/laravel.');

        if (! (bool) $this->option('no-config')) {
            $this->components->task('Publishing config/access.php', function (): bool {
                $this->callSilent('vendor:publish', ['--tag' => 'access-config']);

                return true;
            });
        }

        if ((bool) $this->option('with-lang')) {
            $this->components->task('Publishing lang/{en,pt_BR}/exceptions.php', function (): bool {
                $this->callSilent('vendor:publish', ['--tag' => 'access-lang']);

                return true;
            });
        }

        if ((bool) $this->option('with-seeder')) {
            $this->components->task('Publishing database/seeders/AccessSeeder.php', function (): bool {
                $this->callSilent('vendor:publish', ['--tag' => 'access-seeder']);

                return true;
            });
        }

        if (! (bool) $this->option('no-migrate')) {
            $this->components->task('Running migrations', function (): bool {
                $this->callSilent('migrate');

                return true;
            });
        }

        $this->printUserTraitSnippet();

        $this->components->info('Installation complete.');

        return self::SUCCESS;
    }

    private function printUserTraitSnippet(): void
    {
        $this->newLine();
        $this->line('Next step: add the trait to your User model.');
        $this->newLine();
        $this->line('    use ModularizeRbac\\Laravel\\Concerns\\HasAccessPermissions;');
        $this->line('');
        $this->line('    class User extends Authenticatable');
        $this->line('    {');
        $this->line('        use HasAccessPermissions;');
        $this->line('    }');
        $this->newLine();

        if ((bool) $this->option('with-seeder')) {
            $this->line('And to seed sample data:  php artisan db:seed --class=AccessSeeder');
            $this->newLine();
        }
    }
}
