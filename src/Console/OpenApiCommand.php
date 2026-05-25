<?php

declare(strict_types=1);

namespace ModularizeRbac\Laravel\Console;

use Illuminate\Console\Command;
use OpenApi\Generator;

/**
 * Generates / verifies the package's OpenAPI 3.1 spec from the
 * attributes on {@see \ModularizeRbac\Laravel\Http\OpenApi\OpenApiDefinition}.
 *
 * Three modes:
 *   --check       Compare on-disk openapi.json with what would be
 *                 generated; exit 1 if they diverge (PR drift gate).
 *   --update      Overwrite openapi.json with the freshly generated
 *                 output (use after annotating a new endpoint).
 *   (default)     Print the generated YAML/JSON to stdout.
 */
class OpenApiCommand extends Command
{
    protected $signature = 'access:openapi
        {--output= : Path to write the spec to (defaults to stdout when omitted)}
        {--format=json : json or yaml}
        {--check : Compare on-disk openapi.json to the generated spec; exit 1 on drift}
        {--update : Overwrite openapi.json at the repo root with the generated spec}';

    protected $description = 'Generate the package OpenAPI 3.1 spec from the OpenApiDefinition attributes.';

    public function handle(): int
    {
        $generator = (new Generator());
        $openapi = $generator->generate([__DIR__.'/../Http/OpenApi']);

        $format = (string) $this->option('format');
        $body = $format === 'yaml' ? $openapi->toYaml() : $openapi->toJson();

        if ((bool) $this->option('check')) {
            return $this->doCheck($body);
        }

        if ((bool) $this->option('update')) {
            $defaultPath = $this->packageRoot().'/openapi.json';
            file_put_contents($defaultPath, $body);
            $this->info("Wrote spec to {$defaultPath}");

            return self::SUCCESS;
        }

        $output = (string) $this->option('output');
        if ($output !== '') {
            file_put_contents($output, $body);
            $this->info("Wrote spec to {$output}");

            return self::SUCCESS;
        }

        $this->line($body);

        return self::SUCCESS;
    }

    private function doCheck(string $generated): int
    {
        $path = $this->packageRoot().'/openapi.json';
        if (! is_file($path)) {
            $this->error("openapi.json not found at {$path}. Run `php artisan access:openapi --update` first.");

            return self::FAILURE;
        }

        $onDisk = (string) file_get_contents($path);
        if (trim($onDisk) !== trim($generated)) {
            $this->error('openapi.json is out of date. Run `php artisan access:openapi --update` and commit the result.');

            return self::FAILURE;
        }

        $this->info('openapi.json is in sync with the generated spec.');

        return self::SUCCESS;
    }

    /**
     * Resolve the package root regardless of where the host calling
     * `php artisan access:openapi` lives. The file we care about is
     * shipped alongside src/, so we walk up from this file's location.
     */
    private function packageRoot(): string
    {
        return dirname(__DIR__, 2);
    }
}
