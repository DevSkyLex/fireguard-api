<?php

declare(strict_types=1);

namespace Tests\Unit\Import\Domain\ValueObject;

use Import\Domain\ValueObject\ImportRowError;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test ImportRowError.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ImportRowError::class)]
final class ImportRowErrorTest extends TestCase
{
  #[Test]
  public function itExposesAllConstructorValues(): void
  {
    $error = new ImportRowError(
      rowNumber: 7,
      code: 'missing_required',
      message: 'Column "type" is required.',
      column: 'type',
    );

    self::assertSame(7, $error->rowNumber);
    self::assertSame('missing_required', $error->code);
    self::assertSame('Column "type" is required.', $error->message);
    self::assertSame('type', $error->column);
  }

  #[Test]
  public function itDefaultsColumnToNull(): void
  {
    $error = new ImportRowError(rowNumber: 3, code: 'quota_exceeded', message: 'Plan limit reached.');

    self::assertNull($error->column);
  }
}
