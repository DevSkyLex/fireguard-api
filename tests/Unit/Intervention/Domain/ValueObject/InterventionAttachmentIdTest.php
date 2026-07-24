<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Domain\ValueObject;

use Intervention\Domain\ValueObject\InterventionAttachmentId;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;

/**
 * Test InterventionAttachmentId.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(InterventionAttachmentId::class)]
final class InterventionAttachmentIdTest extends TestCase
{
  private const string UUID = '550e8400-e29b-41d4-a716-446655440000';

  #[Test]
  public function testFromStringWrapsAValidUuid(): void
  {
    $id = InterventionAttachmentId::fromString(self::UUID);

    self::assertSame(self::UUID, $id->value);
    self::assertSame(self::UUID, (string) $id);
  }

  #[Test]
  public function testFromStringRejectsAnInvalidUuid(): void
  {
    $this->expectException(InvalidValueException::class);

    InterventionAttachmentId::fromString('not-a-uuid');
  }

  #[Test]
  public function testEqualsComparesByValue(): void
  {
    $a = InterventionAttachmentId::fromString(self::UUID);
    $b = InterventionAttachmentId::fromString(self::UUID);
    $c = InterventionAttachmentId::fromString('550e8400-e29b-41d4-a716-446655440001');

    self::assertTrue($a->equals($b));
    self::assertFalse($a->equals($c));
  }
}
