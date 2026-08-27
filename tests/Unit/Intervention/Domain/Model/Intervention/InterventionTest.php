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

use function str_repeat;

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
  public function itReschedulesAPlannedIntervention(): void
  {
    $policy = new InterventionTransitionPolicy();
    $intervention = $this->intervention();
    $intervention->transitionTo(InterventionStatus::PLANNED, $policy);

    $intervention->edit(
      policy: $policy,
      priority: InterventionPriority::URGENT,
      plannedStartAt: new DateTimeImmutable('2026-07-03T08:00:00+00:00'),
      dueAt: new DateTimeImmutable('2026-07-04T18:00:00+00:00'),
      hasPriority: true,
      hasPlannedStartAt: true,
      hasDueAt: true,
    );

    self::assertSame(InterventionPriority::URGENT, $intervention->priority());
    self::assertSame('2026-07-03T08:00:00+00:00', $intervention->plannedStartAt()?->format('c'));
  }

  #[Test]
  public function itReschedulesWhileFieldWorkIsInProgress(): void
  {
    $policy = new InterventionTransitionPolicy();
    $intervention = $this->intervention();
    $intervention->transitionTo(InterventionStatus::PLANNED, $policy);
    $intervention->transitionTo(InterventionStatus::IN_PROGRESS, $policy);

    $intervention->edit(
      policy: $policy,
      participants: ['member-9'],
      dueAt: new DateTimeImmutable('2026-07-09T18:00:00+00:00'),
      hasParticipants: true,
      hasDueAt: true,
    );

    self::assertSame(['member-9'], $intervention->participants());
    self::assertSame('2026-07-09T18:00:00+00:00', $intervention->dueAt()?->format('c'));
  }

  #[Test]
  public function itFreezesTheResponsibleOnceFieldWorkStarted(): void
  {
    $policy = new InterventionTransitionPolicy();
    $intervention = $this->intervention();
    $intervention->transitionTo(InterventionStatus::PLANNED, $policy);
    $intervention->edit(policy: $policy, responsibleId: 'member-2', hasResponsibleId: true);
    self::assertSame('member-2', $intervention->responsibleId());

    $intervention->transitionTo(InterventionStatus::IN_PROGRESS, $policy);

    $this->expectException(InterventionConflictException::class);
    $intervention->edit(policy: $policy, responsibleId: 'member-3', hasResponsibleId: true);
  }

  #[Test]
  public function itFreezesEverySubmittedPlanningField(): void
  {
    $policy = new InterventionTransitionPolicy();
    $intervention = $this->intervention();
    $intervention->transitionTo(InterventionStatus::PLANNED, $policy);
    $intervention->transitionTo(InterventionStatus::IN_PROGRESS, $policy);
    $intervention->transitionTo(InterventionStatus::SUBMITTED, $policy);

    $this->expectException(InterventionConflictException::class);
    $intervention->edit(
      policy: $policy,
      dueAt: new DateTimeImmutable('2026-08-01T18:00:00+00:00'),
      hasDueAt: true,
    );
  }

  #[Test]
  public function itRefusesClearingAPlanningValueOutsideDraft(): void
  {
    $policy = new InterventionTransitionPolicy();
    $intervention = $this->intervention();
    $intervention->transitionTo(InterventionStatus::PLANNED, $policy);

    $this->expectException(InterventionConflictException::class);
    $intervention->edit(policy: $policy, dueAt: null, hasDueAt: true);
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

  #[Test]
  public function itAllowsWithdrawingASubmissionBackToInProgress(): void
  {
    $intervention = $this->intervention();
    $policy = new InterventionTransitionPolicy();
    $intervention->transitionTo(InterventionStatus::PLANNED, $policy);
    $intervention->transitionTo(InterventionStatus::IN_PROGRESS, $policy);
    $intervention->transitionTo(InterventionStatus::SUBMITTED, $policy);

    $intervention->transitionTo(InterventionStatus::IN_PROGRESS, $policy);

    self::assertSame(InterventionStatus::IN_PROGRESS, $intervention->status());
  }

  #[Test]
  public function itCreatesADraftWithNormalizedFields(): void
  {
    $intervention = Intervention::create(
      id: 'intervention-1',
      organizationId: '  organization-1  ',
      type: InterventionType::SITE_SETUP,
      name: '  Kickoff  ',
      siteId: '  site-1  ',
      responsibleId: '  member-1  ',
      participants: ['member-2', 'member-2'],
      priority: InterventionPriority::LOW,
      plannedStartAt: new DateTimeImmutable('2026-06-15T08:00:00+00:00'),
      dueAt: new DateTimeImmutable('2026-06-15T18:00:00+00:00'),
      description: '  Prepare the site  ',
    );

    self::assertSame('intervention-1', $intervention->id());
    self::assertSame('organization-1', $intervention->organizationId());
    self::assertSame(InterventionType::SITE_SETUP, $intervention->type());
    self::assertSame('Kickoff', $intervention->name());
    self::assertSame('Prepare the site', $intervention->description());
    self::assertSame(InterventionStatus::DRAFT, $intervention->status());
    self::assertSame('site-1', $intervention->siteId());
    self::assertSame('member-1', $intervention->responsibleId());
    self::assertSame(['member-2'], $intervention->participants());
    self::assertSame(InterventionPriority::LOW, $intervention->priority());
    self::assertNull($intervention->reviewNote());
    self::assertSame(1, $intervention->revision());
    self::assertSame($intervention->createdAt(), $intervention->updatedAt());
  }

  #[Test]
  public function itRejectsABlankOrganizationIdOnCreate(): void
  {
    $this->expectException(InterventionConflictException::class);
    Intervention::create(
      id: 'intervention-1',
      organizationId: '   ',
      type: InterventionType::INVENTORY,
      name: 'Inventory',
      siteId: null,
      responsibleId: null,
      participants: [],
      priority: InterventionPriority::NORMAL,
      plannedStartAt: null,
      dueAt: null,
    );
  }

  #[Test]
  public function itRejectsANameLongerThan160CharactersOnCreate(): void
  {
    $this->expectException(InterventionConflictException::class);
    Intervention::create(
      id: 'intervention-1',
      organizationId: 'organization-1',
      type: InterventionType::INVENTORY,
      name: str_repeat('a', 161),
      siteId: null,
      responsibleId: null,
      participants: [],
      priority: InterventionPriority::NORMAL,
      plannedStartAt: null,
      dueAt: null,
    );
  }

  #[Test]
  public function itRejectsADueDateNotAfterPlannedStartOnCreate(): void
  {
    $this->expectException(InterventionConflictException::class);
    Intervention::create(
      id: 'intervention-1',
      organizationId: 'organization-1',
      type: InterventionType::INVENTORY,
      name: 'Inventory',
      siteId: null,
      responsibleId: null,
      participants: [],
      priority: InterventionPriority::NORMAL,
      plannedStartAt: new DateTimeImmutable('2026-06-15T18:00:00+00:00'),
      dueAt: new DateTimeImmutable('2026-06-15T08:00:00+00:00'),
    );
  }

  #[Test]
  public function itReconstitutesAnInterventionFromPersistedState(): void
  {
    $createdAt = new DateTimeImmutable('2026-06-01T00:00:00+00:00');
    $updatedAt = new DateTimeImmutable('2026-06-10T00:00:00+00:00');
    $plannedStartAt = new DateTimeImmutable('2026-06-15T08:00:00+00:00');
    $dueAt = new DateTimeImmutable('2026-06-15T18:00:00+00:00');

    $intervention = Intervention::reconstitute(
      id: 'intervention-9',
      organizationId: 'organization-1',
      type: InterventionType::INSPECTION_CAMPAIGN,
      name: 'Annual campaign',
      description: 'Full sweep',
      status: InterventionStatus::SUBMITTED,
      siteId: 'site-3',
      responsibleId: 'member-7',
      participants: ['member-2', 'member-3'],
      priority: InterventionPriority::HIGH,
      plannedStartAt: $plannedStartAt,
      dueAt: $dueAt,
      reviewNote: 'looks good',
      revision: 7,
      createdAt: $createdAt,
      updatedAt: $updatedAt,
    );

    self::assertSame('intervention-9', $intervention->id());
    self::assertSame('organization-1', $intervention->organizationId());
    self::assertSame(InterventionType::INSPECTION_CAMPAIGN, $intervention->type());
    self::assertSame('Annual campaign', $intervention->name());
    self::assertSame('Full sweep', $intervention->description());
    self::assertSame(InterventionStatus::SUBMITTED, $intervention->status());
    self::assertSame('site-3', $intervention->siteId());
    self::assertSame('member-7', $intervention->responsibleId());
    self::assertSame(['member-2', 'member-3'], $intervention->participants());
    self::assertSame(InterventionPriority::HIGH, $intervention->priority());
    self::assertSame($plannedStartAt, $intervention->plannedStartAt());
    self::assertSame($dueAt, $intervention->dueAt());
    self::assertSame('looks good', $intervention->reviewNote());
    self::assertSame(7, $intervention->revision());
    self::assertSame($createdAt, $intervention->createdAt());
    self::assertSame($updatedAt, $intervention->updatedAt());
  }

  #[Test]
  public function itRenamesAMutableIntervention(): void
  {
    $intervention = $this->intervention();

    $intervention->rename('  Renamed  ');

    self::assertSame('Renamed', $intervention->name());
    self::assertSame(2, $intervention->revision());
  }

  #[Test]
  public function itRejectsRenamingAPublishedIntervention(): void
  {
    $intervention = $this->published();

    $this->expectException(InterventionConflictException::class);
    $intervention->rename('Renamed');
  }

  #[Test]
  public function itChangesTheDescriptionAndClearsItWhenBlank(): void
  {
    $intervention = $this->intervention();

    $intervention->changeDescription('  Updated notes  ');
    self::assertSame('Updated notes', $intervention->description());

    $intervention->changeDescription('   ');
    self::assertNull($intervention->description());

    $intervention->changeDescription(null);
    self::assertNull($intervention->description());
  }

  #[Test]
  public function itChangesPlanningFieldsWhileStillDraft(): void
  {
    $policy = new InterventionTransitionPolicy();
    $intervention = $this->intervention();

    $intervention->edit(
      policy: $policy,
      siteId: '  site-2  ',
      responsibleId: null,
      participants: ['a', 'a', 'b'],
      priority: InterventionPriority::URGENT,
      hasSiteId: true,
      hasResponsibleId: true,
      hasParticipants: true,
      hasPriority: true,
    );

    self::assertSame('site-2', $intervention->siteId());
    self::assertNull($intervention->responsibleId());
    self::assertSame(['a', 'b'], $intervention->participants());
    self::assertSame(InterventionPriority::URGENT, $intervention->priority());
  }

  #[Test]
  public function itRejectsRescheduleWithDueBeforePlannedStart(): void
  {
    $intervention = $this->intervention();

    $this->expectException(InterventionConflictException::class);
    $intervention->edit(
      policy: new InterventionTransitionPolicy(),
      plannedStartAt: new DateTimeImmutable('2026-07-01T18:00:00+00:00'),
      dueAt: new DateTimeImmutable('2026-07-01T08:00:00+00:00'),
      hasPlannedStartAt: true,
      hasDueAt: true,
    );
  }

  #[Test]
  public function itFreezesTheSiteAfterPlanning(): void
  {
    $policy = new InterventionTransitionPolicy();
    $intervention = $this->intervention();
    $intervention->transitionTo(InterventionStatus::PLANNED, $policy);

    $this->expectException(InterventionConflictException::class);
    $intervention->edit(policy: $policy, siteId: 'site-2', hasSiteId: true);
  }

  #[Test]
  public function itChangesTheReviewNote(): void
  {
    $intervention = $this->intervention();

    $intervention->changeReviewNote('  needs rework  ');

    self::assertSame('needs rework', $intervention->reviewNote());
    self::assertSame(2, $intervention->revision());
  }

  #[Test]
  public function itTransitionsThroughTheLifecycleToChangesRequested(): void
  {
    $intervention = $this->intervention();
    $policy = new InterventionTransitionPolicy();

    $intervention->transitionTo(InterventionStatus::PLANNED, $policy);
    $intervention->transitionTo(InterventionStatus::IN_PROGRESS, $policy);
    $intervention->transitionTo(InterventionStatus::SUBMITTED, $policy);
    $intervention->changeReviewNote('Fix the layout');
    $intervention->transitionTo(InterventionStatus::CHANGES_REQUESTED, $policy);

    self::assertSame(InterventionStatus::CHANGES_REQUESTED, $intervention->status());
  }

  #[Test]
  public function itAbandonsADraftAndBecomesImmutable(): void
  {
    $intervention = $this->intervention();

    $intervention->transitionTo(InterventionStatus::ABANDONED, new InterventionTransitionPolicy());
    self::assertSame(InterventionStatus::ABANDONED, $intervention->status());

    $this->expectException(InterventionConflictException::class);
    $intervention->rename('Renamed');
  }

  #[Test]
  public function itRejectsAnIllegalTransition(): void
  {
    $intervention = $this->intervention();

    $this->expectException(InterventionConflictException::class);
    $intervention->transitionTo(InterventionStatus::IN_PROGRESS, new InterventionTransitionPolicy());
  }

  #[Test]
  public function itRejectsTransitioningAPublishedIntervention(): void
  {
    $intervention = $this->published();

    $this->expectException(InterventionConflictException::class);
    $intervention->transitionTo(InterventionStatus::ABANDONED, new InterventionTransitionPolicy());
  }

  #[Test]
  public function itEditsEveryFieldAndTransitionsStatus(): void
  {
    $intervention = $this->intervention();
    $plannedStartAt = new DateTimeImmutable('2026-07-01T08:00:00+00:00');
    $dueAt = new DateTimeImmutable('2026-07-01T18:00:00+00:00');

    $intervention->edit(
      policy: new InterventionTransitionPolicy(),
      name: '  New name  ',
      description: '  New description  ',
      siteId: '  site-9  ',
      responsibleId: '  member-9  ',
      participants: ['a', 'a', 'b'],
      priority: InterventionPriority::URGENT,
      plannedStartAt: $plannedStartAt,
      dueAt: $dueAt,
      reviewNote: '  a note  ',
      nextStatus: InterventionStatus::PLANNED,
      hasName: true,
      hasDescription: true,
      hasSiteId: true,
      hasResponsibleId: true,
      hasParticipants: true,
      hasPriority: true,
      hasPlannedStartAt: true,
      hasDueAt: true,
      hasReviewNote: true,
    );

    self::assertSame('New name', $intervention->name());
    self::assertSame('New description', $intervention->description());
    self::assertSame('site-9', $intervention->siteId());
    self::assertSame('member-9', $intervention->responsibleId());
    self::assertSame(['a', 'b'], $intervention->participants());
    self::assertSame(InterventionPriority::URGENT, $intervention->priority());
    self::assertSame($plannedStartAt, $intervention->plannedStartAt());
    self::assertSame($dueAt, $intervention->dueAt());
    self::assertSame('a note', $intervention->reviewNote());
    self::assertSame(InterventionStatus::PLANNED, $intervention->status());
    self::assertSame(2, $intervention->revision());
  }

  #[Test]
  public function itIgnoresAPriorityEditWhenNoPriorityIsProvided(): void
  {
    $intervention = $this->intervention();

    $intervention->edit(
      policy: new InterventionTransitionPolicy(),
      priority: null,
      hasPriority: true,
    );

    self::assertSame(InterventionPriority::NORMAL, $intervention->priority());
    self::assertSame(2, $intervention->revision());
  }

  #[Test]
  public function itRejectsEditingTheNameWithABlankValue(): void
  {
    $intervention = $this->intervention();

    $this->expectException(InterventionConflictException::class);
    $intervention->edit(
      policy: new InterventionTransitionPolicy(),
      name: null,
      hasName: true,
    );
  }

  #[Test]
  public function itRejectsEditingPlanningFieldsAfterPlanning(): void
  {
    $intervention = $this->intervention();
    $intervention->transitionTo(InterventionStatus::PLANNED, new InterventionTransitionPolicy());

    $this->expectException(InterventionConflictException::class);
    $intervention->edit(
      policy: new InterventionTransitionPolicy(),
      siteId: 'site-2',
      hasSiteId: true,
    );
  }

  #[Test]
  public function itRejectsEditingAnImmutableIntervention(): void
  {
    $intervention = $this->published();

    $this->expectException(InterventionConflictException::class);
    $intervention->edit(new InterventionTransitionPolicy(), name: 'Renamed', hasName: true);
  }

  private function intervention(): Intervention
  {
    return Intervention::create(
      id: 'intervention-1',
      organizationId: 'organization-1',
      type: InterventionType::INVENTORY,
      name: 'Inventory',
      siteId: 'site-1',
      responsibleId: 'member-1',
      participants: ['member-2'],
      priority: InterventionPriority::NORMAL,
      plannedStartAt: new DateTimeImmutable('2026-06-15T08:00:00+00:00'),
      dueAt: new DateTimeImmutable('2026-06-15T18:00:00+00:00'),
    );
  }

  private function published(): Intervention
  {
    return Intervention::reconstitute(
      id: 'intervention-9',
      organizationId: 'organization-1',
      type: InterventionType::INSPECTION_CAMPAIGN,
      name: 'Published campaign',
      description: 'Done',
      status: InterventionStatus::PUBLISHED,
      siteId: 'site-1',
      responsibleId: 'member-1',
      participants: ['member-2'],
      priority: InterventionPriority::HIGH,
      plannedStartAt: new DateTimeImmutable('2026-06-15T08:00:00+00:00'),
      dueAt: new DateTimeImmutable('2026-06-15T18:00:00+00:00'),
      reviewNote: 'ok',
      revision: 7,
      createdAt: new DateTimeImmutable('2026-06-01T00:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-06-10T00:00:00+00:00'),
    );
  }
}
