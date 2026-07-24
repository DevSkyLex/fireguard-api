<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Domain\ValueObject;

use Facility\Domain\ValueObject\FacilityAttachmentId;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;

/**
 * Test FacilityAttachmentId.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(FacilityAttachmentId::class)]
final class FacilityAttachmentIdTest extends TestCase
{
  private const string UUID = '550e8400-e29b-41d4-a716-446655441234';

  #[Test]
  public function testFromStringCreatesInstance(): void
  {
    $id = FacilityAttachmentId::fromString(self::UUID);

    self::assertSame(self::UUID, $id->value);
    self::assertSame(self::UUID, (string) $id);
  }

  #[Test]
  public function testEqualsReturnsTrueForSameValue(): void
  {
    self::assertTrue(
      FacilityAttachmentId::fromString(self::UUID)->equals(FacilityAttachmentId::fromString(self::UUID)),
    );
  }

  #[Test]
  public function testFromStringThrowsOnInvalidUuid(): void
  {
    $this->expectException(InvalidValueException::class);

    FacilityAttachmentId::fromString('1234');
  }
}
