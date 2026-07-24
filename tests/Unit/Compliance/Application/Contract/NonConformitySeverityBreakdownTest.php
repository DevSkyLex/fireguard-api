<?php

declare(strict_types=1);

namespace Tests\Unit\Compliance\Application\Contract;

use Compliance\Application\Contract\NonConformitySeverityBreakdown;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test NonConformitySeverityBreakdownTest.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(NonConformitySeverityBreakdown::class)]
final class NonConformitySeverityBreakdownTest extends TestCase
{
  #[Test]
  public function testConstructorExposesEachSeverityCount(): void
  {
    $breakdown = new NonConformitySeverityBreakdown(low: 1, medium: 2, high: 3, critical: 4);

    self::assertSame(1, $breakdown->low);
    self::assertSame(2, $breakdown->medium);
    self::assertSame(3, $breakdown->high);
    self::assertSame(4, $breakdown->critical);
  }

  #[Test]
  public function testConstructorDefaultsEverySeverityToZero(): void
  {
    $breakdown = new NonConformitySeverityBreakdown();

    self::assertSame(0, $breakdown->low);
    self::assertSame(0, $breakdown->medium);
    self::assertSame(0, $breakdown->high);
    self::assertSame(0, $breakdown->critical);
  }
}
