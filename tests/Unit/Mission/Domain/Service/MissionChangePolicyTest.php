<?php

declare(strict_types=1);

namespace Tests\Unit\Mission\Domain\Service;

use Mission\Domain\Exception\MissionConflictException;
use Mission\Domain\Service\MissionChangePolicy;
use Mission\Domain\ValueObject\MissionStatus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MissionChangePolicyTest extends TestCase
{
  private MissionChangePolicy $policy;

  protected function setUp(): void
  {
    $this->policy = new MissionChangePolicy();
  }

  #[Test]
  public function itAllowsFieldAgentsToCreateAndEditChangesDuringExecution(): void
  {
    $this->policy->assertCanCreate(MissionStatus::IN_PROGRESS);
    $this->policy->assertCanEditPatch(MissionStatus::CHANGES_REQUESTED);

    self::addToAssertionCount(2);
  }

  #[Test]
  public function itAllowsReviewersToRejectButNotEditSubmittedChanges(): void
  {
    $this->policy->assertCanChangeStatus(MissionStatus::SUBMITTED, 'rejected');

    $this->expectException(MissionConflictException::class);
    $this->policy->assertCanEditPatch(MissionStatus::SUBMITTED);
  }

  #[Test]
  public function itRejectsNewChangesAfterSubmission(): void
  {
    $this->expectException(MissionConflictException::class);
    $this->policy->assertCanCreate(MissionStatus::SUBMITTED);
  }
}
