<?php

namespace Sitetus\LaravelCollector\Tests;

use Illuminate\Support\Facades\Route;

final class DisabledWrapperTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        // No project key / public key: the provider must not expose a route.
        $app['config']->set('sitetus-collector', [
            'enabled' => true,
            'project_key' => '',
            'public_key' => '',
            'algo' => 'ed25519',
            'expires_at' => null,
            'install_path' => '',
        ]);
    }

    public function testNoRouteWithoutConfiguration(): void
    {
        $this->assertFalse(Route::has('sitetus.collector'));
    }
}
