<?php

declare(strict_types=1);

namespace Tests\Unit\Assistant\Domain\ValueObject;

use Assistant\Domain\ValueObject\AssistantMessageId;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;

/**
 * Test AssistantMessageId.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(AssistantMessageId::class)]
final class AssistantMessageIdTest extends TestCase
{
  private const string VALID_UUID = '018f0b68-6758-7a12-8a1d-3f0d97f64c01';

  #[Test]
  public function testFromStringWrapsAValidUuid(): void
  {
    $id = AssistantMessageId::fromString(self::VALID_UUID);

    self::assertSame(self::VALID_UUID, $id->value);
    self::assertSame(self::VALID_UUID, (string) $id);
  }

  #[Test]
  public function testEqualsComparesByValue(): void
  {
    $id = AssistantMessageId::fromString(self::VALID_UUID);
    $same = AssistantMessageId::fromString(self::VALID_UUID);

    self::assertTrue($id->equals($same));
  }

  #[Test]
  public function testFromStringRejectsAnInvalidUuid(): void
  {
    $this->expectException(InvalidValueException::class);

    AssistantMessageId::fromString('not-a-uuid');
  }
}
