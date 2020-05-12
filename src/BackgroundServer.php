<?php

namespace Enflow\Component\Testing;

class BackgroundServer
{
    private $process;

    public function start()
    {
        $pipes = [];

        $this->process = proc_open("php -S 0.0.0.0:8000 -t public", [
            ['pipe', 'r'],
            ['pipe', 'w'], // stdout
            ['pipe', 'w'], // stderr
        ], $pipes, getcwd(), $this->getEnv());
    }

    public function stop()
    {
        if ($this->process) {
            proc_terminate($this->process);
        }
    }

    protected function getEnv(): array
    {
        $envPairs = [];
        foreach ($this->getDefaultEnv() as $k => $v) {
            if (false !== $v && in_array($k, $this->envToPassthrough())) {
                $envPairs[] = $k.'='.$v;
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
