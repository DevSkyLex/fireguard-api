<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Domain\ValueObject;

use Intervention\Domain\Exception\InterventionValidationException;
use Intervention\Domain\ValueObject\WorkItemPeriod;
use PHPUnit\Framework\TestCase;

final class WorkItemPeriodTest extends TestCase
{
  public function testInheritsAnUnscheduledIntervention(): void
  {
    self::assertTrue(new WorkItemPeriod()->fitsWithin(null, null));
  }

  public function testRequiresBothDates(): void
  {
    $this->expectException(InterventionValidationException::class);
    new WorkItemPeriod('2026-09-16');
  }

  public function testBoundsAreInclusive(): void
  {
    $period = new WorkItemPeriod('2026-09-16', '2026-09-17');
    self::assertTrue($period->fitsWithin('2026-09-16', '2026-09-17'));
    self::assertFalse($period->fitsWithin('2026-09-17', '2026-09-18'));
    self::assertFalse($period->fitsWithin(null, null));
  }

  public function testRejectsNormalizedInvalidDates(): void
  {
    $this->expectException(InterventionValidationException::class);
    new WorkItemPeriod('2026-02-30', '2026-03-01');
  }
}
