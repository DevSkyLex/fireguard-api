<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Domain\ValueObject;

use Equipment\Domain\ValueObject\TagId;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;

/**
 * Test TagIdTest.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(TagId::class)]
final class TagIdTest extends TestCase
{
  private const string VALID_UUID = '550e8400-e29b-41d4-a716-446655440050';

  #[Test]
  public function itCreatesFromValidUuid(): void
  {
    $id = TagId::fromString(self::VALID_UUID);

    self::assertSame(self::VALID_UUID, $id->value);
    self::assertSame(self::VALID_UUID, (string) $id);
  }

  #[Test]
  public function itRejectsInvalidUuid(): void
  {
    $this->expectException(InvalidValueException::class);

    TagId::fromString('xxx');
  }
}
