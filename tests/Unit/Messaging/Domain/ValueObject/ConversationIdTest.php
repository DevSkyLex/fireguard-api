<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Domain\ValueObject;

use Messaging\Domain\ValueObject\ConversationId;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;

/**
 * Test ConversationId.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ConversationId::class)]
final class ConversationIdTest extends TestCase
{
  private const string VALID = '11111111-1111-4111-8111-111111111111';

  #[Test]
  public function fromStringWrapsAValidUuid(): void
  {
    $id = ConversationId::fromString(self::VALID);

    self::assertSame(self::VALID, $id->value);
    self::assertSame(self::VALID, (string) $id);
  }

  #[Test]
  public function equalsComparesByValue(): void
  {
    $id = ConversationId::fromString(self::VALID);
    $same = ConversationId::fromString(self::VALID);
    $other = ConversationId::fromString('22222222-2222-4222-8222-222222222222');

    self::assertTrue($id->equals($same));
    self::assertFalse($id->equals($other));
  }

  #[Test]
  public function fromStringRejectsAnInvalidUuid(): void
  {
    $this->expectException(InvalidValueException::class);

    ConversationId::fromString('not-a-uuid');
  }
}
