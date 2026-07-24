<?php

declare(strict_types=1);

namespace Tests\Unit\Import\Domain\ValueObject;

use Import\Domain\ValueObject\ImportStatus;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test ImportStatus.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ImportStatus::class)]
final class ImportStatusTest extends TestCase
{
  #[Test]
  public function itExposesTheExpectedBackingValues(): void
  {
    self::assertSame('pending', ImportStatus::PENDING->value);
    self::assertSame('processing', ImportStatus::PROCESSING->value);
    self::assertSame('completed', ImportStatus::COMPLETED->value);
    self::assertSame('failed', ImportStatus::FAILED->value);
  }

  #[Test]
  public function itReturnsAllSupportedValues(): void
  {
    self::assertSame(['pending', 'processing', 'completed', 'failed'], ImportStatus::values());
  }

  #[Test]
  public function itFlagsCompletedAndFailedAsTerminal(): void
  {
    self::assertTrue(ImportStatus::COMPLETED->isTerminal());
    self::assertTrue(ImportStatus::FAILED->isTerminal());
  }

  #[Test]
  public function itDoesNotFlagPendingOrProcessingAsTerminal(): void
  {
    self::assertFalse(ImportStatus::PENDING->isTerminal());
    self::assertFalse(ImportStatus::PROCESSING->isTerminal());
  }
}
