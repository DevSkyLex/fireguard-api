<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Domain\ValueObject;

use Organization\Domain\ValueObject\PlanId;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;

/**
 * Test PlanId.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(PlanId::class)]
final class PlanIdTest extends TestCase
{
  private const string VALID = '22222222-2222-4222-8222-222222222222';

  #[Test]
  public function testFromStringBuildsAPlanId(): void
  {
    $id = PlanId::fromString(self::VALID);

    self::assertSame(self::VALID, $id->value);
    self::assertSame(self::VALID, (string) $id);
  }

  #[Test]
  public function testFromStringRejectsAMalformedUuid(): void
  {
    $this->expectException(InvalidValueException::class);

    PlanId::fromString('not-a-uuid');
  }

  #[Test]
  public function testEqualsComparesTheUnderlyingValue(): void
  {
    self::assertTrue(PlanId::fromString(self::VALID)->equals(PlanId::fromString(self::VALID)));
    self::assertFalse(
      PlanId::fromString(self::VALID)->equals(PlanId::fromString('33333333-3333-4333-8333-333333333333')),
    );
  }
}
