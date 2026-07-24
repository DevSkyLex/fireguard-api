<?php

declare(strict_types=1);

namespace Tests\Unit\Import\Domain\Exception;

use Import\Domain\Exception\ImportJobNotFoundException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Test ImportJobNotFoundException.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ImportJobNotFoundException::class)]
final class ImportJobNotFoundExceptionTest extends TestCase
{
  #[Test]
  public function itBuildsWithTheOffendingId(): void
  {
    $exception = ImportJobNotFoundException::withId('job-42');

    self::assertInstanceOf(RuntimeException::class, $exception);
    self::assertSame('Import job with ID "job-42" not found.', $exception->getMessage());
  }
}
