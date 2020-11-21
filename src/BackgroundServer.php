<?php

namespace Enflow\Component\Testing;

use Exception;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class BackgroundServer
{
    public Process $process;

    public function start()
    {
        $env = collect($this->getEnv())->join(' ');

        $command = "env -i {$env} php -S 0.0.0.0:8000 -t public";

        $this->process = Process::fromShellCommandline($command);
        $this->process->setTty(Process::isTtySupported());
        $this->process->disableOutput();
        $this->process->start();

        $tries = 0;
        do {
            $isRunning = $this->isRunning();

            $tries++;
        } while ($isRunning === false && $tries < 100);
    }

    public function stop()
    {
        // First kill main process.
        if ($this->process->isRunning()) {
            $this->process->stop();
        }

        dump(`ps aux --headings | cat | grep php`);

        // The process that's still running is the subprocess. Let's kill that seperatly.
        $pid = trim(`ps aux --no-headings | cat | grep "php -S" | grep -v grep | awk '{ print $2 }' | head -1`, "\n");
        if (is_numeric($pid)) {
            dump('Killing ' . $pid);
            posix_kill($pid, 9);
        }

        dump(`ps aux --headings | cat | grep php`);
    }

    protected function getEnv(): array
    {
        $envPairs = [];
        foreach ($this->getDefaultEnv() as $k => $v) {
            if (false !== $v && in_array($k, $this->envToPassthrough())) {
                $envPairs[] = $k . '=' . $v;
            }
        }

        return $envPairs;
    }

    protected function getDefaultEnv(): array
    {
        $env = [];

        foreach ($_SERVER as $k => $v) {
            if (\is_string($v) && false !== $v = getenv($k)) {
                $env[$k] = $v;
            }
        }

        foreach ($_ENV as $k => $v) {
            if (\is_string($v)) {
                $env[$k] = $v;
            }
        }

        return $env;
    }

    private function envToPassthrough()
    {
        return [
            'DB_CONNECTION',
            'DB_HOST',
            'DB_PORT',
            'DB_DATABASE',
            'DB_USERNAME',
            'DB_PASSWORD',
            'REDIS_HOST',
            'REDIS_PORT',
        ];
    }

    private function isRunning()
    {
        $ch = curl_init('http://127.0.0.1:8000');
        curl_setopt($ch, CURLOPT_TIMEOUT, 2);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 1);
        curl_exec($ch);
        $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpStatus === 200;
    }
}
