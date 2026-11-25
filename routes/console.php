<?php

use Illuminate\Console\Scheduling\Schedule;

Schedule::command('inbounds:lock-morning')
    ->everyMinute()
    ->withoutOverlapping();
