<?php

declare(strict_types=1);

namespace Shared\Application\Port\Outbound;

use Shared\Domain\ValueObject\RateLimitResult;

/**
 * Interface RateLimiterPort.
 *
 * Port for rate limiting operations.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface RateLimiterPort
{
    /**
     * Method consume.
     *
     * Attempts to consume a token from the rate limiter.
     *
     * @since 1.0.0
     *
     * @param string $key    the rate limiter key
     * @param int    $tokens number of tokens to consume
     *
     * @return RateLimitResult the result
     */
    public function consume(string $key, int $tokens = 1): RateLimitResult;

    /**
     * Method reset.
     *
     * Resets the rate limiter for a key.
     *
     * @since 1.0.0
     *
     * @param string $key the rate limiter key
     */
    public function reset(string $key): void;
}
