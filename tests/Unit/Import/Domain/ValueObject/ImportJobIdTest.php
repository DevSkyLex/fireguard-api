<?php

declare(strict_types=1);

namespace Tests\Unit\Import\Domain\ValueObject;

use Import\Domain\ValueObject\ImportJobId;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;

/**
 * Test ImportJobId.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ImportJobId::class)]
final class ImportJobIdTest extends TestCase
{
  private const string VALID_UUID = '018f0b68-6758-7a12-8a1d-3f0d97f65a01';

  #[Test]
  public function itBuildsFromAValidUuidString(): void
  {
    $id = ImportJobId::fromString(self::VALID_UUID);

    self::assertSame(self::VALID_UUID, $id->value);
    self::assertSame(self::VALID_UUID, (string) $id);
  }

  #[Test]
  public function itConsidersTwoEqualValuesEqual(): void
  {
    $left = ImportJobId::fromString(self::VALID_UUID);
    $right = ImportJobId::fromString(self::VALID_UUID);

    self::assertTrue($left->equals($right));
  }

  #[Test]
  public function itRejectsAnInvalidUuidString(): void
  {
    $this->expectException(InvalidValueException::class);

    ImportJobId::fromString('not-a-uuid');
  }
}
