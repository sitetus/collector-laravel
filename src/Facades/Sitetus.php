<?php

namespace Sitetus\LaravelCollector\Facades;

use Illuminate\Support\Facades\Facade;
use Sitetus\LaravelCollector\CustomChecks;

/**
 * @method static void check(string $id, callable $callback) Register a developer-defined check; the callback returns a \Sitetus\LaravelCollector\CheckOutcome.
 *
 * @see CustomChecks
 */
final class Sitetus extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return CustomChecks::class;
    }

    public static function check(string $id, callable $callback): void
    {
        static::getFacadeRoot()->register($id, $callback);
    }
}
