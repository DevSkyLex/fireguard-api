<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Domain\Model\TimeEntry;

use Intervention\Domain\Exception\{InterventionPreconditionFailedException, InterventionValidationException};
use Intervention\Domain\Model\TimeEntry\TimeEntry;
use Intervention\Domain\ValueObject\WorkItemEffort;
use PHPUnit\Framework\TestCase;

final class TimeEntryTest extends TestCase
{
  public function testTimeAndRemainingEffortAreIndependent(): void
  {
    $effort = new WorkItemEffort(300, 180);
    $entry = new TimeEntry('entry', 'task', 'former-assignee', '2026-09-15', 120, null);
    self::assertSame(180, $effort->remainingMinutes);
    self::assertSame(120, $entry->minutes);
  }

  public function testCorrectionPreservesOldVersionAndContributor(): void
  {
    $original = new TimeEntry('entry', 'task', 'former-assignee', '2026-09-15', 120, null);
    $corrected = $original->correct(1, '2026-09-14', 90, 'Correction');
    self::assertSame(120, $original->minutes);
    self::assertSame('former-assignee', $corrected->memberId);
    self::assertSame(2, $corrected->revision);
    self::assertTrue($corrected->cancel(2)->cancelled);
  }

  public function testStaleCorrectionIsRejected(): void
  {
    $this->expectException(InterventionPreconditionFailedException::class);
    new TimeEntry('entry', 'task', 'member', '2026-09-15', 120, null, 2)->correct(1, '2026-09-15', 90, null);
  }

  public function testZeroActualTimeIsRejected(): void
  {
    $this->expectException(InterventionValidationException::class);
    new TimeEntry('entry', 'task', 'member', '2026-09-15', 0, null);
  }
}
