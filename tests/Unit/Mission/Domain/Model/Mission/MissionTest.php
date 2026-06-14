<?php

declare(strict_types=1);

namespace Tests\Unit\Mission\Domain\Model\Mission;

use DateTimeImmutable;
use Mission\Domain\Exception\MissionConflictException;
use Mission\Domain\Model\Mission\Mission;
use Mission\Domain\Service\MissionTransitionPolicy;
use Mission\Domain\ValueObject\{MissionPriority, MissionStatus, MissionType};
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MissionTest extends TestCase
{
  #[Test]
  public function itAppliesOneRepresentationPatchWithOneRevisionIncrement(): void
  {
    $mission = $this->mission();

    $mission->edit(
      policy: new MissionTransitionPolicy(),
      name: 'North site inventory',
      participants: ['member-2', 'member-2', 'member-3'],
      priority: MissionPriority::HIGH,
      hasName: true,
      hasParticipants: true,
      hasPriority: true,
    );

    self::assertSame(2, $mission->revision());
    self::assertSame('North site inventory', $mission->name());
    self::assertSame(['member-2', 'member-3'], $mission->participants());
    self::assertSame(MissionPriority::HIGH, $mission->priority());
  }

  #[Test]
  public function itRequiresPreparedScopeBeforePlanning(): void
  {
    $mission = Mission::create(
      id: 'mission-1',
      organizationId: 'organization-1',
      type: MissionType::INVENTORY,
      name: 'Inventory',
      referencePackId: 'fr-erp-ert-v1',
      siteId: null,
      responsibleId: null,
      participants: [],
      priority: MissionPriority::NORMAL,
      plannedStartAt: null,
      dueAt: null,
    );

    $this->expectException(MissionConflictException::class);
    $mission->transitionTo(MissionStatus::PLANNED, new MissionTransitionPolicy());
  }

  #[Test]
  public function itFreezesPlanningFieldsAfterPlanning(): void
  {
    $mission = $this->mission();
    $mission->transitionTo(MissionStatus::PLANNED, new MissionTransitionPolicy());

    $this->expectException(MissionConflictException::class);
    $mission->changePriority(MissionPriority::URGENT);
  }

  #[Test]
  public function itRequiresAReviewNoteWhenRequestingChanges(): void
  {
    $mission = $this->mission();
    $policy = new MissionTransitionPolicy();
    $mission->transitionTo(MissionStatus::PLANNED, $policy);
    $mission->transitionTo(MissionStatus::IN_PROGRESS, $policy);
    $mission->transitionTo(MissionStatus::SUBMITTED, $policy);

    $this->expectException(MissionConflictException::class);
    $mission->transitionTo(MissionStatus::CHANGES_REQUESTED, $policy);
  }

  private function mission(): Mission
  {
    return Mission::create(
      id: 'mission-1',
      organizationId: 'organization-1',
      type: MissionType::INVENTORY,
      name: 'Inventory',
      referencePackId: 'fr-erp-ert-v1',
      siteId: 'site-1',
      responsibleId: 'member-1',
      participants: ['member-2'],
      priority: MissionPriority::NORMAL,
      plannedStartAt: new DateTimeImmutable('2026-06-15T08:00:00+00:00'),
      dueAt: new DateTimeImmutable('2026-06-15T18:00:00+00:00'),
    );
  }
}
