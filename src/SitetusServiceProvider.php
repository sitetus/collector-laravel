<?php

namespace Sitetus\LaravelCollector;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Sitetus\LaravelCollector\Http\CollectorController;
use Sitetus\LaravelCollector\Jobs\WriteQueueHeartbeat;

/**
 * Composer wrapper: registers the pull route, publishes the
 * config and — unlike the single file — records the scheduler and queue
 * heartbeats itself, so no line is added to the customer's code.
 */
final class SitetusServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/sitetus-collector.php', 'sitetus-collector');

        $this->app->singleton(CustomChecks::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/sitetus-collector.php' => config_path('sitetus-collector.php'),
        ], 'sitetus-collector-config');

        if (! $this->enabled()) {
            return;
        }

        $installPath = (string) config('sitetus-collector.install_path');

        Route::get('/sitetus/'.$installPath, CollectorController::class)
            ->middleware('api')
            ->name('sitetus.collector');

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->call(function (): void {
                @file_put_contents(
                    storage_path('app/sitetus-heartbeat.json'),
                    json_encode(['ts' => time(), 'php' => PHP_VERSION]),
                );

                dispatch(new WriteQueueHeartbeat);
            })->name('sitetus-heartbeat')->everyFiveMinutes();
        });
    }

    private function enabled(): bool
    {
        return (bool) config('sitetus-collector.enabled')
            && filled(config('sitetus-collector.project_key'))
            && filled(config('sitetus-collector.public_key'))
            && filled(config('sitetus-collector.install_path'));
    }
}
