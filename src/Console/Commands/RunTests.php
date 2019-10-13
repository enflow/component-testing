<?php

namespace Enflow\Component\Testing\Console\Commands;

use Illuminate\Console\Command;

class RunTests extends Command
{
    protected $signature = 'test';

    protected $description = 'Runs the test scripts for the project where component-testing is installed';

    public function handle()
    {

    }
}
