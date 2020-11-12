<?php

namespace Enflow\Component\Testing;

use Exception;

class BackgroundServer
{
    private static $pid;

    public static function start()
    {
        $output = [];
        exec("php -S 0.0.0.0:8000 -t public >/dev/null 2>&1 & echo $!", $output);

        static::$pid = (int)$output[0] ?? null;

        sleep(3); // TODO: implement a better way to see a php server startup failure

        if (posix_getpgid(static::$pid) === false) {
            static::$pid = null;
        }

        if (empty(static::$pid)) {
            throw new Exception("Failed to start background testing server; no pid returned");
        }
    }

    public static function stop()
    {
        if (static::isRunning()) {
            exec('kill ' . static::$pid);
        }
    }

    public static function isRunning(): bool
    {
        return !empty(static::$pid);
    }
}
