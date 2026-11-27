<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('inbounds:lock-morning')
    ->everyMinute()
    ->withoutOverlapping();
