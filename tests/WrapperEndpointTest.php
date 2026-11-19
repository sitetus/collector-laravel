<?php

namespace Sitetus\LaravelCollector\Tests;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Route;
use Sitetus\LaravelCollector\CheckOutcome;
use Sitetus\LaravelCollector\CustomChecks;

final class WrapperEndpointTest extends TestCase
{
    private const PATH = '/sitetus/'.self::INSTALL_PATH;

    public function testUnsignedRequestIsAnAnonymousBodylessNotFound(): void
    {
        $response = $this->get(self::PATH);

        $response->assertNotFound();
        $this->assertSame('', $response->getContent());
    }

    /**
     * Core 0.7.4+: a caller that names the right project key but fails the
     * signature gets a bodyless 403 with the reason, so a broken pairing can be told
     * apart from a missing file. A caller without the key still gets the 404 above.
     */
    public function testTamperedSignatureIsForbiddenWithItsReason(): void
    {
        $headers = $this->signedHeaders(self::PATH);
        $headers['X-Sitetus-Signature'] = base64_encode(str_repeat('x', 64));

        $response = $this->get(self::PATH, $headers);

        $response->assertForbidden();
        $response->assertHeader('X-Sitetus-Error', 'signature');
        $this->assertSame('', $response->getContent());
    }

    public function testSignedRequestReturnsTheWrapperEnvelope(): void
    {
        $response = $this->get(self::PATH, $this->signedHeaders(self::PATH));

        $response->assertOk();
        $envelope = $response->json();

        $this->assertSame(1, $envelope['schema_version']);
        $this->assertSame('sitetus-laravel', $envelope['collector']['name']);
        $this->assertSame('php', $envelope['collector']['lang']);
        $this->assertSame(self::PROJECT_KEY, $envelope['project_key']);
        $this->assertSame('monitor', $envelope['mode']);
        $this->assertSame(2, $envelope['level']);
        $this->assertSame(PHP_VERSION, $envelope['runtime']['php_version']);

        // Parity with the single file: same top-level sections,
        // same Level 2 check ids from the shared Level2::checksFromApp().
        foreach (['generated_at', 'runtime', 'framework', 'meta', 'health'] as $section) {
            $this->assertArrayHasKey($section, $envelope);
        }

        $checkIds = array_column($envelope['health']['checks'], 'id');
        $this->assertContains('app_bootstrap', $checkIds);
        $this->assertContains('db_connectivity', $checkIds);
        $this->assertContains('flag_app_debug', $checkIds);
        // 0.6.0 posture rows ride along; the link check carries flags, not paths.
        $this->assertContains('storage_link', $checkIds);
        $this->assertContains('session_driver', $checkIds);
        // 0.7.0: maintenance_mode comes from Level 1 exactly once (no Level 2 duplicate).
        $this->assertSame(1, count(array_keys($checkIds, 'maintenance_mode', true)));

        // Minimality: the flag ids are fine, the actual secret values are not.
        $flat = (string) json_encode($envelope);
        $this->assertStringNotContainsString(public_path(), $flat);
        $this->assertStringNotContainsString((string) config('app.key'), $flat);
        $this->assertStringNotContainsString('password', strtolower($flat));

        // The wrapper runs the configured modules (default: all).
        $this->assertSame(['files', 'archives', 'permissions'], $envelope['modules']['enabled']);
        $this->assertArrayHasKey('tracked', $envelope['modules']['files']);
        $this->assertArrayHasKey('count', $envelope['modules']['archives']);
    }

    public function testModulesCanBeSwitchedOffByConfig(): void
    {
        config()->set('sitetus-collector.modules', '');

        $envelope = $this->get(self::PATH, $this->signedHeaders(self::PATH))->assertOk()->json();

        $this->assertArrayNotHasKey('modules', $envelope);
    }

    public function testNonceReplayIsRejected(): void
    {
        $headers = $this->signedHeaders(self::PATH);

        $this->get(self::PATH, $headers)->assertOk();
        $this->get(self::PATH, $headers)
            ->assertForbidden()
            ->assertHeader('X-Sitetus-Error', 'nonce');
    }

    public function testClockSkewGetsDiagnosticHeaders(): void
    {
        $headers = $this->signedHeaders(self::PATH, time() - 700);

        $this->get(self::PATH, $headers)
            ->assertForbidden()
            ->assertHeader('X-Sitetus-Error', 'skew');
    }

    public function testCustomChecksLandInTheEnvelope(): void
    {
        $this->app->make(CustomChecks::class)->register('orders_last_24h', fn (): CheckOutcome => CheckOutcome::ok(42));

        $envelope = $this->get(self::PATH, $this->signedHeaders(self::PATH))->json();

        $this->assertSame(
            [['id' => 'orders_last_24h', 'status' => 'ok', 'value' => 42]],
            $envelope['custom_checks'],
        );
    }

    public function testRouteAndHeartbeatAreRegistered(): void
    {
        $this->assertTrue(Route::has('sitetus.collector'));

        $descriptions = array_map(
            fn ($event): string => (string) $event->description,
            $this->app->make(Schedule::class)->events(),
        );

        $this->assertContains('sitetus-heartbeat', $descriptions);
    }
}
