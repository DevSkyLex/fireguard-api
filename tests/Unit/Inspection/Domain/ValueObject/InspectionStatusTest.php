<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Domain\ValueObject;

use Inspection\Domain\ValueObject\InspectionStatus;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test InspectionStatus.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(InspectionStatus::class)]
final class InspectionStatusTest extends TestCase
{
  #[Test]
  public function itExposesAllValues(): void
  {
    self::assertSame(['draft', 'submitted', 'closed', 'cancelled'], InspectionStatus::values());
  }

  #[Test]
  public function itReportsDraftState(): void
  {
    self::assertTrue(InspectionStatus::DRAFT->isDraft());
    self::assertFalse(InspectionStatus::SUBMITTED->isDraft());
  }

  #[Test]
  public function itReportsSubmittedState(): void
  {
    self::assertTrue(InspectionStatus::SUBMITTED->isSubmitted());
    self::assertFalse(InspectionStatus::DRAFT->isSubmitted());
  }

  #[Test]
  public function itReportsClosedState(): void
  {
    self::assertTrue(InspectionStatus::CLOSED->isClosed());
    self::assertFalse(InspectionStatus::DRAFT->isClosed());
  }

  #[Test]
  public function itReportsCancelledState(): void
  {
    self::assertTrue(InspectionStatus::CANCELLED->isCancelled());
    self::assertFalse(InspectionStatus::DRAFT->isCancelled());
  }
}
