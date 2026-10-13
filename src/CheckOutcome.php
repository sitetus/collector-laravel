<?php

namespace Sitetus\LaravelCollector;

use InvalidArgumentException;

/**
 * Result of one developer-defined check. Only {id, status, value?}
 * ever reaches the envelope; free text is rejected by design, so value is
 * limited to numbers and booleans.
 */
final class CheckOutcome
{
    public const STATUSES = ['ok', 'warn', 'fail', 'unknown'];

    public readonly string $status;

    public readonly int|float|bool|null $value;

    private function __construct(string $status, mixed $value)
    {
        if (! in_array($status, self::STATUSES, true)) {
            throw new InvalidArgumentException("Invalid check status [{$status}].");
        }

        if ($value !== null && ! is_int($value) && ! is_float($value) && ! is_bool($value)) {
            throw new InvalidArgumentException('Custom check values may only be numbers or booleans - never text.');
        }

        $this->status = $status;
        $this->value = $value;
    }

    /**
     * $value is deliberately untyped: a union type would silently coerce a
     * string to bool, letting free text slip through instead of throwing.
     */
    public static function ok(mixed $value = null): self
    {
        return new self('ok', $value);
    }

    public static function warn(mixed $value = null): self
    {
        return new self('warn', $value);
    }

    public static function fail(mixed $value = null): self
    {
        return new self('fail', $value);
    }

    public static function unknown(): self
    {
        return new self('unknown', null);
    }
}
