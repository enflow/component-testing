<?php

namespace Enflow\Component\Testing;

use Illuminate\Support\ServiceProvider;

class TestingServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->commands([
            Console\Commands\RunTests::class,
        ]);
    }
}
