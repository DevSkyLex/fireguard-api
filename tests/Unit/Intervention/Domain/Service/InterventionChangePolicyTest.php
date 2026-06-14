<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Domain\Service;

use Intervention\Domain\Exception\InterventionConflictException;
use Intervention\Domain\Service\InterventionChangePolicy;
use Intervention\Domain\ValueObject\InterventionStatus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class InterventionChangePolicyTest extends TestCase
{
  private InterventionChangePolicy $policy;

  protected function setUp(): void
  {
    $this->policy = new InterventionChangePolicy();
  }

  #[Test]
  public function itAllowsFieldAgentsToCreateAndEditChangesDuringExecution(): void
  {
    $this->policy->assertCanCreate(InterventionStatus::IN_PROGRESS);
    $this->policy->assertCanEditPatch(InterventionStatus::CHANGES_REQUESTED);

    self::addToAssertionCount(2);
  }

  #[Test]
  public function itAllowsReviewersToRejectButNotEditSubmittedChanges(): void
  {
    $this->policy->assertCanChangeStatus(InterventionStatus::SUBMITTED, 'rejected');

    $this->expectException(InterventionConflictException::class);
    $this->policy->assertCanEditPatch(InterventionStatus::SUBMITTED);
  }

  #[Test]
  public function itRejectsNewChangesAfterSubmission(): void
  {
    $this->expectException(InterventionConflictException::class);
    $this->policy->assertCanCreate(InterventionStatus::SUBMITTED);
  }
}
