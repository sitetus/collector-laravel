<?php

namespace Sitetus\LaravelCollector\Tests;

use PHPUnit\Framework\TestCase;
use Sitetus\Collector\Config;
use Sitetus\Collector\Envelope;
use Sitetus\LaravelCollector\CheckOutcome;
use Sitetus\LaravelCollector\CustomChecks;

final class CustomChecksTest extends TestCase
{
    public function testResultsCarryOnlyIdStatusValue(): void
    {
        $checks = new CustomChecks();
        $checks->register('orders_last_24h', function () {
            return CheckOutcome::ok(42);
        });
        $checks->register('backups_ok', function () {
            return CheckOutcome::fail(false);
        });

        $results = $checks->results();

        $this->assertSame(
            [
                ['id' => 'orders_last_24h', 'status' => 'ok', 'value' => 42],
                ['id' => 'backups_ok', 'status' => 'fail', 'value' => false],
            ],
            $results
        );

        foreach ($results as $result) {
            $this->assertSame([], array_diff(array_keys($result), ['id', 'status', 'value']));
        }
    }

    public function testFreeTextValuesAreRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('never text');

        CheckOutcome::ok('/var/www/secret leaked into an envelope');
    }

    public function testInvalidIdsAreRejected(): void
    {
        $checks = new CustomChecks();

        $this->expectException(\InvalidArgumentException::class);

        $checks->register('Bad Id With Spaces', function () {
            return CheckOutcome::ok();
        });
    }

    public function testThrowingCheckDegradesToUnknown(): void
    {
        $checks = new CustomChecks();
        $checks->register('explodes', function () {
            throw new \RuntimeException('customer code failure');
        });

        $this->assertSame([['id' => 'explodes', 'status' => 'unknown']], $checks->results());
    }

    public function testEnvelopeGrowsACustomChecksSection(): void
    {
        $config = Config::fromArray([
            'project_key' => 'p', 'public_key' => '', 'algo' => 'ed25519',
            'expires_at' => '', 'root_override' => '', 'nonce_file' => '.n.json',
        ], sys_get_temp_dir());
        $config->collectorName = 'sitetus-laravel';

        $envelope = (new Envelope($config))->build(
            2,
            ['detected' => 'laravel'],
            null,
            [],
            null,
            'override',
            [],
            microtime(true),
            __FILE__,
            [['id' => 'orders_last_24h', 'status' => 'ok', 'value' => 42]]
        );

        $this->assertSame('sitetus-laravel', $envelope['collector']['name']);
        $this->assertSame([['id' => 'orders_last_24h', 'status' => 'ok', 'value' => 42]], $envelope['custom_checks']);
    }
}
