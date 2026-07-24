<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Domain\ValueObject;

use Inspection\Domain\ValueObject\NonConformitySeverity;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test NonConformitySeverity.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(NonConformitySeverity::class)]
final class NonConformitySeverityTest extends TestCase
{
  #[Test]
  public function itExposesAllValues(): void
  {
    self::assertSame(['low', 'medium', 'high', 'critical'], NonConformitySeverity::values());
  }

  #[Test]
  public function itResolvesFromBackingValue(): void
  {
    self::assertSame(NonConformitySeverity::CRITICAL, NonConformitySeverity::from('critical'));
  }
}
