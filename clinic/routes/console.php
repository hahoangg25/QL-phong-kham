<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Tác vụ tự động (HT-01, HT-03, HT-04, HT-10, QT-09)
|--------------------------------------------------------------------------
| Môi trường phát triển: php artisan schedule:work
| Máy chủ: cron "* * * * * php /path/artisan schedule:run"
*/

Schedule::command('clinic:release-holds')->everyMinute()->withoutOverlapping();
Schedule::command('clinic:send-reminders')->everyTenMinutes()->withoutOverlapping();
Schedule::command('clinic:mark-no-shows')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('clinic:generate-slots')->dailyAt('00:30');
Schedule::command('clinic:backup')->dailyAt('02:00');
Schedule::command('activitylog:clean')->monthly();
