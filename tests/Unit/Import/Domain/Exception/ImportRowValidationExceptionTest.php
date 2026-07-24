<?php

declare(strict_types=1);

namespace Tests\Unit\Import\Domain\Exception;

use Import\Domain\Exception\ImportRowValidationException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Test ImportRowValidationException.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ImportRowValidationException::class)]
final class ImportRowValidationExceptionTest extends TestCase
{
  #[Test]
  public function itDefaultsToTheInvalidCodeAndNoColumn(): void
  {
    $exception = new ImportRowValidationException('Something went wrong.');

    self::assertInstanceOf(RuntimeException::class, $exception);
    self::assertSame('Something went wrong.', $exception->getMessage());
    self::assertSame('invalid', $exception->errorCode);
    self::assertNull($exception->column);
  }

  #[Test]
  public function itBuildsAMissingRequiredColumnFailure(): void
  {
    $exception = ImportRowValidationException::missingRequiredColumn('name');

    self::assertSame('Column "name" is required.', $exception->getMessage());
    self::assertSame('missing_required', $exception->errorCode);
    self::assertSame('name', $exception->column);
  }

  #[Test]
  public function itBuildsAnInvalidColumnFailure(): void
  {
    $exception = ImportRowValidationException::invalidColumn('type', 'unknown value');

    self::assertSame('Column "type" is invalid: unknown value', $exception->getMessage());
    self::assertSame('invalid', $exception->errorCode);
    self::assertSame('type', $exception->column);
  }
}
