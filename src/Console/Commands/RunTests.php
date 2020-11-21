<?php

namespace Enflow\Component\Testing\Console\Commands;

use Enflow\Component\Testing\BackgroundServer;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\ExecutableFinder;

class RunTests extends Command
{
    protected $signature = 'run-tests 
        {--filter= : filters classes or methods} 
        {--repeat= : run tests specified number of times}
        {--no-dusk : only executes phpunit tests}
        {--only-dusk : skip phpunit tests}';

    protected $description = 'Runs the test scripts with a background server running for Dusk tests.';

    protected ?BackgroundServer $backgroundServer = null;

    public function handle()
    {
        $this->setupBackgroundServer();

        $this->runTests();
    }

    private function runTests(): void
    {
        // Dump out debug info
        if ($this->getOutput()->isVerbose() || env('CI')) {
            $this->dumpDebugInfo();
        }

        // Ensure the chrome driver is always running with the latest version
        if ($this->shouldRunDuskTests()) {
            if (! file_exists(base_path('.env.dusk'))) {
                throw new Exception(".env.dusk file is required.");
            }

            $majorVersion = trim((Process::fromShellCommandline('/opt/google/chrome/chrome --version | cut -d " " -f3 | cut -d "." -f1'))->mustRun()->getOutput(), "\n");

            $this->info("");

            $this->info("Upgrading Dusk Chrome Driver to {$majorVersion}");

            $this->script("php artisan dusk:chrome-driver {$majorVersion}");

            $this->info("");
        }

        // Ensure that the database.sqlite file exists & clear it if it does
//        touch(base_path('database/database.sqlite'));
//
//        $filter = ($filter = $this->option('filter')) ? "--filter={$filter}" : null;
//        $repeat = ($repeat = $this->option('repeat')) ? "--repeat={$repeat}" : null;
//
//        if (! $this->option('only-dusk')) {
//            $this->script("./vendor/bin/phpunit --cache-result --order-by=defects --stop-on-failure --testdox {$filter} {$repeat}");
//        }
//
//        if ($this->shouldRunDuskTests()) {
//            $this->script("php artisan dusk --cache-result --order-by=defects --stop-on-failure --testdox {$filter} {$repeat}", [
//                'APP_URL' => 'http://localhost:8000',
//                'APP_DOMAIN' => 'localhost:8000',
//            ]);
//        }
    }

    private function script(string $script, array $env = []): void
    {
        $this->info("> " . $script);

        $process = Process::fromShellCommandline($script, base_path(), $env);
        $process->setTimeout(60 * 15 * 15);
        $process->setTty(Process::isTtySupported());

        $process->run(function ($type, $line) {
            $this->output->write($line);
        });

        if ($process->getExitCode() !== 0) {
            exit($process->getExitCode());
        }
    }

    private function setupBackgroundServer(): void
    {
        register_shutdown_function(function () {
            if ($this->backgroundServer) {
                $this->info("Stopping background server");
                $this->backgroundServer->stop();
            }
        });

        // Only use background server for dusk tests
        if ($this->shouldRunDuskTests()) {
            $this->info("Starting background server");

            $this->backgroundServer = new BackgroundServer();
            $this->backgroundServer->start();
        }
    }

    private function shouldRunDuskTests(): bool
    {
        if ($this->option('no-dusk')) {
            return false;
        }

        return file_exists(base_path('tests/Browser'));
    }

    private function dumpDebugInfo()
    {
        $this->info("Debug info\n" . json_encode([
                'app' => Arr::only(config('app'), [
                    'name',
                    'env',
                    'url',
                ]),
                'database' => Arr::only(config('database.connections.' . config('database.default')), [
                    'driver',
                    'database',
                    'host',
                    'port',
                    'username',
                    'password',
                ]),
            ], JSON_PRETTY_PRINT));

        $this->info("");
    }
}
