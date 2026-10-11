<?php
/**
 * Accounting Blade integration preflight (run inside the FULL Laravel app):
 *   php scripts/check_accounting_blades.php
 *
 * Uses Laravel's real BladeCompiler, then php -l on each compiled PHP view.
 * This catches errors that static Blade directive-count checks can miss.
 * No database writes or migrations are performed.
 */

declare(strict_types=1);

$project = dirname(__DIR__);
$autoload = $project . '/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "Laravel vendor/autoload.php missing. Run this script in your installed RMS project.\n");
    exit(2);
}
require $autoload;
$app = require $project . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$compiler = app('blade.compiler');
$viewDir = resource_path('views/admin/accounting');
if (!is_dir($viewDir)) {
    fwrite(STDERR, "Accounting views directory not found: $viewDir\n");
    exit(2);
}

$views = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($viewDir, FilesystemIterator::SKIP_DOTS));
$total = 0;
$failed = 0;
foreach ($views as $view) {
    if (!$view->isFile() || !str_ends_with($view->getFilename(), '.blade.php')) {
        continue;
    }
    $total++;
    $relative = substr($view->getPathname(), strlen($viewDir) + 1);
    $tmp = tempnam(sys_get_temp_dir(), 'acc_blade_');
    if ($tmp === false) {
        fwrite(STDERR, "Could not allocate a temporary file.\n");
        exit(2);
    }

    try {
        // Compile the exact view without rendering or querying live financial data.
        $compiled = $compiler->compileString((string) file_get_contents($view->getPathname()));
        file_put_contents($tmp, $compiled);
        $process = new \Symfony\Component\Process\Process([PHP_BINARY, '-l', $tmp]);
        $process->run();
        if (!$process->isSuccessful()) {
            $failed++;
            echo "FAIL: {$relative}\n{$process->getErrorOutput()}{$process->getOutput()}\n";
        } else {
            echo "PASS: {$relative}\n";
        }
    } catch (\Throwable $exception) {
        $failed++;
        echo "FAIL: {$relative}: {$exception->getMessage()}\n";
    } finally {
        @unlink($tmp);
    }
}

printf("Accounting Blade checks: %d passed, %d failed (total %d).\n", $total - $failed, $failed, $total);
exit($failed > 0 ? 1 : 0);
