<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Domain\ValueObject;

use Facility\Domain\ValueObject\FacilityId;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;

/**
 * Test FacilityId.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(FacilityId::class)]
final class FacilityIdTest extends TestCase
{
  private const string UUID = '550e8400-e29b-41d4-a716-446655440000';

  #[Test]
  public function testFromStringCreatesInstance(): void
  {
    $id = FacilityId::fromString(self::UUID);

    self::assertSame(self::UUID, $id->value);
    self::assertSame(self::UUID, (string) $id);
  }

  #[Test]
  public function testConstructorAcceptsValidUuid(): void
  {
    $id = new FacilityId(self::UUID);

    self::assertSame(self::UUID, $id->value);
  }

  #[Test]
  public function testEqualsReturnsTrueForSameValue(): void
  {
    self::assertTrue(FacilityId::fromString(self::UUID)->equals(FacilityId::fromString(self::UUID)));
  }

  #[Test]
  public function testEqualsReturnsFalseForDifferentValue(): void
  {
    $other = FacilityId::fromString('550e8400-e29b-41d4-a716-446655440001');

    self::assertFalse(FacilityId::fromString(self::UUID)->equals($other));
  }

  #[Test]
  public function testFromStringThrowsOnInvalidUuid(): void
  {
    $this->expectException(InvalidValueException::class);

    FacilityId::fromString('not-a-uuid');
  }
}
