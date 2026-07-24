<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Domain\ValueObject;

use Facility\Domain\ValueObject\FacilityOrganizationId;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;

/**
 * Test FacilityOrganizationId.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(FacilityOrganizationId::class)]
final class FacilityOrganizationIdTest extends TestCase
{
  private const string UUID = '550e8400-e29b-41d4-a716-4466554420ff';

  #[Test]
  public function testFromStringCreatesInstance(): void
  {
    $id = FacilityOrganizationId::fromString(self::UUID);

    self::assertSame(self::UUID, $id->value);
    self::assertSame(self::UUID, (string) $id);
  }

  #[Test]
  public function testEqualsReturnsTrueForSameValue(): void
  {
    self::assertTrue(
      FacilityOrganizationId::fromString(self::UUID)->equals(FacilityOrganizationId::fromString(self::UUID)),
    );
  }

  #[Test]
  public function testEqualsReturnsFalseForDifferentValue(): void
  {
    $other = FacilityOrganizationId::fromString('550e8400-e29b-41d4-a716-4466554420fe');

    self::assertFalse(FacilityOrganizationId::fromString(self::UUID)->equals($other));
  }

  #[Test]
  public function testFromStringThrowsOnEmptyString(): void
  {
    $this->expectException(InvalidValueException::class);

    FacilityOrganizationId::fromString('');
  }
}
