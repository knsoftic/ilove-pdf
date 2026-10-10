<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduled tasks
|--------------------------------------------------------------------------
|
| Add a single cron entry on the server:
|
|   * * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
|
*/

// Delete expired uploads and results.
Schedule::command('pdf:cleanup')->everyFiveMinutes()->withoutOverlapping()->runInBackground();

// Keep the queue tidy.
Schedule::command('queue:prune-failed --hours=48')->daily();
