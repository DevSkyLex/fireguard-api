<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Domain\ValueObject;

use Intervention\Domain\Exception\InterventionValidationException;
use Intervention\Domain\ValueObject\WorkItemEffort;
use PHPUnit\Framework\TestCase;

final class WorkItemEffortTest extends TestCase
{
  public function testUnknownIsNotZero(): void
  {
    self::assertNull(WorkItemEffort::estimated(null)->remainingMinutes);
    self::assertSame(0, WorkItemEffort::estimated(0)->remainingMinutes);
  }

  public function testRemainingIsIndependentOfReferenceEstimate(): void
  {
    $effort = WorkItemEffort::estimated(300)->reestimate(180);
    self::assertSame(300, $effort->estimatedMinutes);
    self::assertSame(180, $effort->remainingMinutes);
  }

  public function testNegativeMinutesAreRejected(): void
  {
    $this->expectException(InterventionValidationException::class);
    WorkItemEffort::estimated(-1);
  }

  public function testFractionalMinutesAreRejected(): void
  {
    $this->expectException(InterventionValidationException::class);
    WorkItemEffort::minutes(2.5);
  }
}
