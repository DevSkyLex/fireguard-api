<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Domain\ValueObject;

use Messaging\Domain\ValueObject\MessageId;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;

/**
 * Test MessageId.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MessageId::class)]
final class MessageIdTest extends TestCase
{
  private const string VALID = '33333333-3333-4333-8333-333333333333';

  #[Test]
  public function fromStringWrapsAValidUuid(): void
  {
    $id = MessageId::fromString(self::VALID);

    self::assertSame(self::VALID, $id->value);
    self::assertSame(self::VALID, (string) $id);
  }

  #[Test]
  public function equalsComparesByValue(): void
  {
    $id = MessageId::fromString(self::VALID);
    $same = MessageId::fromString(self::VALID);
    $other = MessageId::fromString('44444444-4444-4444-8444-444444444444');

    self::assertTrue($id->equals($same));
    self::assertFalse($id->equals($other));
  }

  #[Test]
  public function fromStringRejectsAnInvalidUuid(): void
  {
    $this->expectException(InvalidValueException::class);

    MessageId::fromString('');
  }
}
