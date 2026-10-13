<?php

namespace Sitetus\LaravelCollector;

use InvalidArgumentException;
use Throwable;

/**
 * Registry for developer-defined checks: the application registers
 * closures via Sitetus::check(); the wrapper runs them during a pull and
 * ships only {id, status, value?} — titles, thresholds and severity live
 * in the panel's own definitions, never in the envelope.
 */
final class CustomChecks
{
    public const MAX_CHECKS = 25;

    /** @var array<string, callable(): CheckOutcome> */
    private array $checks = [];

    /**
     * @param callable(): CheckOutcome $callback
     */
    public function register(string $id, callable $callback): void
    {
        if (preg_match('/^[a-z0-9_]{1,64}$/', $id) !== 1) {
            throw new InvalidArgumentException("Custom check ids are snake_case slugs, got [{$id}].");
        }

        if (count($this->checks) >= self::MAX_CHECKS && ! isset($this->checks[$id])) {
            throw new InvalidArgumentException('At most '.self::MAX_CHECKS.' custom checks may be registered.');
        }

        $this->checks[$id] = $callback;
    }

    /**
     * @return array<int, array{id: string, status: string, value?: int|float|bool}>
     */
    public function results(): array
    {
        $results = [];

        foreach ($this->checks as $id => $callback) {
            try {
                $outcome = $callback();

                if (! $outcome instanceof CheckOutcome) {
                    $outcome = CheckOutcome::unknown();
                }
            } catch (Throwable) {
                $outcome = CheckOutcome::unknown();
            }

            $result = ['id' => $id, 'status' => $outcome->status];

            if ($outcome->value !== null) {
                $result['value'] = $outcome->value;
            }

            $results[] = $result;
        }

        return $results;
    }

    /**
     * @return array<int, string>
     */
    public function ids(): array
    {
        return array_keys($this->checks);
    }
}
