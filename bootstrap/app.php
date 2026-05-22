<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role'               => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission'         => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'api.limit'          => \App\Http\Middleware\ApiRateLimiter::class,
            'log.activity'       => \App\Http\Middleware\LogActivity::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule) {
        // Kirim digest harga harian — jam sesuai setting (default 08:00)
        try {
            $digestTime = \App\Models\PriceAlertSetting::get('digest_time', '08:00');
        } catch (\Throwable $e) {
            $digestTime = '08:00';
        }
        [$hour, $minute] = explode(':', $digestTime);

        $schedule->job(new \App\Jobs\SendDailyPriceDigest)
                 ->dailyAt("{$hour}:{$minute}")
                 ->name('send-daily-price-digest')
                 ->withoutOverlapping();
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();