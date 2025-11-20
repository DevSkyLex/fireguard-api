<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Symfony\Adapter\Outbound;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Shared\Infrastructure\Symfony\Adapter\Outbound\SystemClockAdapter;

/**
 * Class SystemClockAdapterTest
 *
 * Unit tests for the SystemClockAdapter.
 *
 * @category Unit Test
 * @package Tests\Unit\Shared\Infrastructure\Symfony\Adapter\Outbound
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 * @covers \Shared\Infrastructure\Symfony\Adapter\Outbound\SystemClockAdapter
 */
final class SystemClockAdapterTest extends TestCase
{
  /**
   * Test that the current time is returned.
   */
  public function testNow(): void
  {
    $adapter = new SystemClockAdapter();
    $now = $adapter->now();

    $this->assertInstanceOf(DateTimeImmutable::class, $now);
    // Allow a small delta for execution time
    $this->assertEqualsWithDelta(time(), $now->getTimestamp(), 1);
  }
}
