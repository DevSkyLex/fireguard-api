<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Domain\ValueObject;

use Equipment\Domain\ValueObject\AttachmentId;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;

/**
 * Test AttachmentIdTest.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(AttachmentId::class)]
final class AttachmentIdTest extends TestCase
{
  private const string VALID_UUID = '550e8400-e29b-41d4-a716-446655440010';

  #[Test]
  public function itCreatesFromValidUuid(): void
  {
    $id = AttachmentId::fromString(self::VALID_UUID);

    self::assertSame(self::VALID_UUID, $id->value);
    self::assertSame(self::VALID_UUID, (string) $id);
  }

  #[Test]
  public function itRejectsInvalidUuid(): void
  {
    $this->expectException(InvalidValueException::class);

    AttachmentId::fromString('invalid');
  }
}
