<?php

namespace Enflow\Component\Testing;

class BackgroundServer
{
    private static $pid;

    public static function start()
    {
        $output = [];
        exec("php -S 0.0.0.0:8000 -t public >/dev/null 2>&1 & echo $!", $output);

        static::$pid = (int)$output[0] ?? null;

        if (empty(static::$pid)) {
            throw new \Exception("Failed to start background testing server; no pid returned");
        }
    }

    public static function stop()
    {
        if (empty(static::$pid)) {
            return;
        }

        exec('kill ' . static::$pid);
    }
}
