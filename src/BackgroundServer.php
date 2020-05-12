<?php

namespace Enflow\Component\Testing;

use Exception;

class BackgroundServer
{
    private $process;
    private $pipes = [];

    public function start()
    {
        $this->process = proc_open("php -S 0.0.0.0:8000 -t public", [
            ['pipe', 'r'],
            ['pipe', 'w'], // stdout
            ['pipe', 'w'], // stderr
        ], $this->pipes, getcwd(), $this->getEnv());

        $status = proc_get_status($this->process);
        if (empty($status['running'])) {
            throw new Exception("PHP server must be running");
        }
    }

    public function stop()
    {
        $status = proc_get_status($this->process);
        if (empty($status['running'])) {
            throw new Exception("PHP server must be running");
        }

        //close all pipes that are still open
        fclose($this->pipes[1]); //stdout
        fclose($this->pipes[2]); //stderr

        //get the parent pid of the process we want to kill
        $ppid = $status['pid'];

        //use ps to get all the children of this process, and kill them
        $pids = preg_split('/\s+/', `ps -o pid --no-heading --ppid $ppid`);
        foreach ($pids as $pid) {
            if (is_numeric($pid)) {
                posix_kill($pid, 9); //9 is the SIGKILL signal
            }
        }

        proc_close($this->process);
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
