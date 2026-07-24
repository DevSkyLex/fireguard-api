<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Domain\ValueObject;

use Messaging\Domain\ValueObject\MessagingAttachmentId;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;

/**
 * Test MessagingAttachmentId.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MessagingAttachmentId::class)]
final class MessagingAttachmentIdTest extends TestCase
{
  private const string VALID = '55555555-5555-4555-8555-555555555555';

  #[Test]
  public function fromStringWrapsAValidUuid(): void
  {
    $id = MessagingAttachmentId::fromString(self::VALID);

    self::assertSame(self::VALID, $id->value);
    self::assertSame(self::VALID, (string) $id);
  }

  #[Test]
  public function fromStringRejectsAnInvalidUuid(): void
  {
    $this->expectException(InvalidValueException::class);

    MessagingAttachmentId::fromString('xyz');
  }
}
