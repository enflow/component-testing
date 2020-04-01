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
    protected $signature = 'run-tests {--filter=}';
    protected $description = 'Runs the test scripts with a background server running for Dusk tests.';

    public function handle()
    {
        $this->setupBackgroundServer();

        $this->runTests();
    }

    private function runTests(): void
    {
        // Ensure the chrome driver is always running with the latest version
        if ($this->shouldRunDuskTests()) {
            if (!file_exists(base_path('.env.dusk'))) {
                throw new Exception(".env.dusk file is required.");
            }

            $googleChrome = (new ExecutableFinder())->find('google-chrome');
            $fullVersion = trim((new Process([$googleChrome, '--product-version']))->mustRun()->getOutput(), "\n");
            $majorVersion = Arr::first(explode('.', $fullVersion));

            $this->info("Chrome version {$fullVersion}");

            $this->script("php artisan dusk:chrome-driver {$majorVersion}");
        }

        // Ensure that the database.sqlite file exists & clear it if it does
        touch(base_path('database/database.sqlite'));

        $filter = ($filter = $this->option('filter')) ? "--filter={$filter}" : null;

        $this->script("./vendor/bin/phpunit --cache-result --order-by=defects --stop-on-failure --testdox {$filter}");

        if ($this->shouldRunDuskTests()) {
            $this->script("php artisan dusk --cache-result --order-by=defects --stop-on-failure --testdox {$filter}", [
                'APP_URL' => 'http://localhost:8000',
                'APP_DOMAIN' => 'localhost:8000',
            ]);
        }
    }

    private function script(string $script, array $env = []): void
    {
        $this->info("> " . $script);

        $process = Process::fromShellCommandline($script, base_path(), $env);
        $process->setTimeout(60 * 15);

        if (Process::isTtySupported()) {
            $process->setTty(true);
        }

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
            if (BackgroundServer::isRunning()) {
                $this->info("Stopping background server");
                BackgroundServer::stop();
            }
        });

        // Only use background server for dusk tests
        if ($this->shouldRunDuskTests()) {
            $this->info("Starting background server");

            BackgroundServer::start();
        }
    }

    private function shouldRunDuskTests(): bool
    {
        return file_exists(base_path('tests/Browser'));
    }
}
