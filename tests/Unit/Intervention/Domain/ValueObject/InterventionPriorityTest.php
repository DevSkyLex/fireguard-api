<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Domain\ValueObject;

use Intervention\Domain\ValueObject\InterventionPriority;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test InterventionPriority.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(InterventionPriority::class)]
final class InterventionPriorityTest extends TestCase
{
  #[Test]
  public function testBackingValuesAreStable(): void
  {
    self::assertSame('low', InterventionPriority::LOW->value);
    self::assertSame('normal', InterventionPriority::NORMAL->value);
    self::assertSame('high', InterventionPriority::HIGH->value);
    self::assertSame('urgent', InterventionPriority::URGENT->value);
  }

  #[Test]
  public function testFromResolvesTheBackingValue(): void
  {
    self::assertSame(InterventionPriority::URGENT, InterventionPriority::from('urgent'));
  }

  #[Test]
  public function testExposesExactlyFourCases(): void
  {
    self::assertCount(4, InterventionPriority::cases());
  }
}
