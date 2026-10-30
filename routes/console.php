<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('signup:send-closing-exports')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('signup:send-daily-summaries')->dailyAt('07:00')->withoutOverlapping();
Schedule::command('signup:delete-expired-signups')->dailyAt('03:00')->withoutOverlapping();
Schedule::command('model:prune')->dailyAt('03:30');
