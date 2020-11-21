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
            // Sanity check
            $ch = curl_init('http://127.0.0.1:8000');
            curl_setopt($ch, CURLOPT_TIMEOUT, 2);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT, 1);
            curl_exec($ch);
            $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $tries++;
        } while ($httpStatus !== 200 && $tries < 100);
    }

    public function stop()
    {
        $pid = `ps aux --no-headings | grep "0.0.0.0:8000" | grep -v grep | awk '{ print $2 }' | head -1`;

        if (is_numeric($pid)) {
            posix_kill($pid, 9);
        }

        $this->process->stop();
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
}
