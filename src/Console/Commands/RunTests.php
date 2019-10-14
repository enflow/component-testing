<?php

namespace Enflow\Component\Testing\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class RunTests extends Command
{
    protected $signature = 'test';
    protected $description = 'Runs the test scripts for the project where component-testing is installed';

    public function handle()
    {
        try {
            if (file_exists(base_path('tests/Browser'))) {
                $this->script("php artisan dusk:chrome-driver");

                $output = [];
                exec("php -S 0.0.0.0:8000 -t public >/dev/null 2>&1 & echo $!", $output);
                $serverPid = (int)$output[0] ?? null;
            }

            touch(base_path('database/database.sqlite'));

            $this->info("Running phpunit");
            $this->script("./vendor/bin/phpunit --cache-result --order-by=defects --stop-on-failure");

            if (file_exists(base_path('tests/Browser'))) {
                $this->info("Running Dusk");
                $this->script("php artisan dusk --cache-result --order-by=defects --stop-on-failure");
            }
        } finally {
            if (!empty($serverPid)) {
                exec('kill ' . $serverPid);
            }
        }

        exit(0);
    }

    private function script(string $script)
    {
        $process = Process::fromShellCommandline($script, base_path());
        if (Process::isTtySupported()) {
            $process->setTty(true);
        }
        $process->setTimeout(300);
        $process->run(function ($type, $line) {
            $this->output->write($line);
        });

        $exitCode = $process->getExitCode();

        if ($exitCode !== 0) {
            exit($exitCode);
        }
    }
}
