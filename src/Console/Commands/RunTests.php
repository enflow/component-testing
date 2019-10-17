<?php

namespace Enflow\Component\Testing\Console\Commands;

use Enflow\Component\Testing\BackgroundServer;
use Illuminate\Console\Command;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class RunTests extends Command
{
    protected $signature = 'test';
    protected $description = 'Runs the test scripts for the project where component-testing is installed';

    public function handle()
    {
        $this->setupBackgroundServer();

        $this->runTests();
    }

    private function runTests(): void
    {
        // Ensure the chrome driver is always running with the latest version
        if ($this->runDuskTests()) {
            $this->script("php artisan dusk:chrome-driver");
        }

        // Ensure that the database.sqlite file exists & clear it if it does
        touch(base_path('database/database.sqlite'));

        $this->info("Running phpunit");
        $this->script("./vendor/bin/phpunit --cache-result --order-by=defects --stop-on-failure");

        if ($this->runDuskTests()) {
            $this->info("Running Dusk");
            $this->script("php artisan dusk --cache-result --order-by=defects --stop-on-failure");
        }
    }

    private function script(string $script): void
    {
        $process = Process::fromShellCommandline($script, base_path());
        $process->setTimeout(300);

        if (Process::isTtySupported()) {
            $process->setTty(true);
        }

        $process->mustRun(function ($type, $line) {
            $this->output->write($line);
        });
    }

    private function setupBackgroundServer(): void
    {
        register_shutdown_function(function () {
            BackgroundServer::stop();
        });

        // Only use background server for dusk tests
        if ($this->runDuskTests()) {
            BackgroundServer::start();
        }
    }

    private function runDuskTests(): bool
    {
        return file_exists(base_path('tests/Browser'));
    }
}
