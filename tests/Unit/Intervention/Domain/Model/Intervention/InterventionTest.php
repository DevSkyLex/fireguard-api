<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Domain\Model\Intervention;

use DateTimeImmutable;
use Intervention\Domain\Exception\InterventionConflictException;
use Intervention\Domain\Model\Intervention\Intervention;
use Intervention\Domain\Service\InterventionTransitionPolicy;
use Intervention\Domain\ValueObject\{InterventionPriority, InterventionStatus, InterventionType};
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class InterventionTest extends TestCase
{
  #[Test]
  public function itAppliesOneRepresentationPatchWithOneRevisionIncrement(): void
  {
    $intervention = $this->intervention();

    $intervention->edit(
      policy: new InterventionTransitionPolicy(),
      name: 'North site inventory',
      participants: ['member-2', 'member-2', 'member-3'],
      priority: InterventionPriority::HIGH,
      hasName: true,
      hasParticipants: true,
      hasPriority: true,
    );

    self::assertSame(2, $intervention->revision());
    self::assertSame('North site inventory', $intervention->name());
    self::assertSame(['member-2', 'member-3'], $intervention->participants());
    self::assertSame(InterventionPriority::HIGH, $intervention->priority());
  }

  #[Test]
  public function itRequiresPreparedScopeBeforePlanning(): void
  {
    $intervention = Intervention::create(
      id: 'intervention-1',
      organizationId: 'organization-1',
      type: InterventionType::INVENTORY,
      name: 'Inventory',
      referencePackId: 'fr-erp-ert-v1',
      siteId: null,
      responsibleId: null,
      participants: [],
      priority: InterventionPriority::NORMAL,
      plannedStartAt: null,
      dueAt: null,
    );

    $this->expectException(InterventionConflictException::class);
    $intervention->transitionTo(InterventionStatus::PLANNED, new InterventionTransitionPolicy());
  }

  #[Test]
  public function itFreezesPlanningFieldsAfterPlanning(): void
  {
    $intervention = $this->intervention();
    $intervention->transitionTo(InterventionStatus::PLANNED, new InterventionTransitionPolicy());

    $this->expectException(InterventionConflictException::class);
    $intervention->changePriority(InterventionPriority::URGENT);
  }

  #[Test]
  public function itRequiresAReviewNoteWhenRequestingChanges(): void
  {
    $intervention = $this->intervention();
    $policy = new InterventionTransitionPolicy();
    $intervention->transitionTo(InterventionStatus::PLANNED, $policy);
    $intervention->transitionTo(InterventionStatus::IN_PROGRESS, $policy);
    $intervention->transitionTo(InterventionStatus::SUBMITTED, $policy);

    $this->expectException(InterventionConflictException::class);
    $intervention->transitionTo(InterventionStatus::CHANGES_REQUESTED, $policy);
  }

  private function intervention(): Intervention
  {
    return Intervention::create(
      id: 'intervention-1',
      organizationId: 'organization-1',
      type: InterventionType::INVENTORY,
      name: 'Inventory',
      referencePackId: 'fr-erp-ert-v1',
      siteId: 'site-1',
      responsibleId: 'member-1',
      participants: ['member-2'],
      priority: InterventionPriority::NORMAL,
      plannedStartAt: new DateTimeImmutable('2026-06-15T08:00:00+00:00'),
      dueAt: new DateTimeImmutable('2026-06-15T18:00:00+00:00'),
    );
  }
}
