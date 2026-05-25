<?php

declare(strict_types=1);

/*
 * Bootstrap for PHPBench: load Composer's autoloader. The benchmark
 * subjects themselves are responsible for booting an Orchestra
 * Testbench application via BenchTestCase::setUpApplication() — they
 * each call it inside `setUp()` so subjects in the same file don't
 * share leaked state.
 */

require __DIR__.'/../vendor/autoload.php';
