<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Domain\Model\Intervention;

use DateTimeImmutable;
use Intervention\Domain\Exception\InterventionConflictException;
use Intervention\Domain\Model\Intervention\{Intervention, InterventionContent, InterventionCreation, InterventionOwnership, InterventionOwnershipChanges, InterventionPatch, InterventionRestoredState, InterventionSchedule, InterventionScheduleChanges, InterventionTextChanges};
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

    $intervention->edit(new InterventionTransitionPolicy(), new InterventionPatch(
      text: new InterventionTextChanges(name: 'North site inventory', hasName: true),
      ownership: new InterventionOwnershipChanges(participants: ['member-2', 'member-2', 'member-3'], hasParticipants: true),
      schedule: new InterventionScheduleChanges(priority: InterventionPriority::HIGH, hasPriority: true),
    ));

    self::assertSame(2, $intervention->revision());
    self::assertSame('North site inventory', $intervention->name());
    self::assertSame(['member-2', 'member-3'], $intervention->participants());
    self::assertSame(InterventionPriority::HIGH, $intervention->priority());
  }

  #[Test]
  public function itRequiresPreparedScopeBeforePlanning(): void
  {
    $intervention = Intervention::create(new InterventionCreation(
      'intervention-1',
      'organization-1',
      InterventionType::INVENTORY,
      new InterventionContent('Inventory', null),
      new InterventionOwnership(null, null, []),
      new InterventionSchedule(InterventionPriority::NORMAL, null, null),
    ));

    $this->expectException(InterventionConflictException::class);
    $intervention->transitionTo(InterventionStatus::PLANNED, new InterventionTransitionPolicy());
  }

  #[Test]
  public function itReschedulesAPlannedIntervention(): void
  {
    $policy = new InterventionTransitionPolicy();
    $intervention = $this->intervention();
    $intervention->transitionTo(InterventionStatus::PLANNED, $policy);

    $intervention->edit($policy, new InterventionPatch(
      schedule: new InterventionScheduleChanges(priority: InterventionPriority::URGENT, plannedStartAt: new DateTimeImmutable('2026-07-03T08:00:00+00:00'), dueAt: new DateTimeImmutable('2026-07-04T18:00:00+00:00'), hasPriority: true, hasPlannedStartAt: true, hasDueAt: true),
    ));

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

    $intervention->edit($policy, new InterventionPatch(
      ownership: new InterventionOwnershipChanges(participants: ['member-9'], hasParticipants: true),
      schedule: new InterventionScheduleChanges(dueAt: new DateTimeImmutable('2026-07-09T18:00:00+00:00'), hasDueAt: true),
    ));

    self::assertSame(['member-9'], $intervention->participants());
    self::assertSame('2026-07-09T18:00:00+00:00', $intervention->dueAt()?->format('c'));
  }

  #[Test]
  public function itFreezesTheResponsibleOnceFieldWorkStarted(): void
  {
    $policy = new InterventionTransitionPolicy();
    $intervention = $this->intervention();
    $intervention->transitionTo(InterventionStatus::PLANNED, $policy);
    $intervention->edit($policy, new InterventionPatch(
      ownership: new InterventionOwnershipChanges(responsibleId: 'member-2', hasResponsibleId: true),
    ));
    self::assertSame('member-2', $intervention->responsibleId());

    $intervention->transitionTo(InterventionStatus::IN_PROGRESS, $policy);

    $this->expectException(InterventionConflictException::class);
    $intervention->edit($policy, new InterventionPatch(
      ownership: new InterventionOwnershipChanges(responsibleId: 'member-3', hasResponsibleId: true),
    ));
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
    $intervention->edit($policy, new InterventionPatch(
      schedule: new InterventionScheduleChanges(dueAt: new DateTimeImmutable('2026-08-01T18:00:00+00:00'), hasDueAt: true),
    ));
  }

  #[Test]
  public function itRefusesClearingAPlanningValueOutsideDraft(): void
  {
    $policy = new InterventionTransitionPolicy();
    $intervention = $this->intervention();
    $intervention->transitionTo(InterventionStatus::PLANNED, $policy);

    $this->expectException(InterventionConflictException::class);
    $intervention->edit($policy, new InterventionPatch(
      schedule: new InterventionScheduleChanges(dueAt: null, hasDueAt: true),
    ));
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
    $intervention = Intervention::create(new InterventionCreation(
      'intervention-1',
      '  organization-1  ',
      InterventionType::SITE_SETUP,
      new InterventionContent('  Kickoff  ', '  Prepare the site  '),
      new InterventionOwnership('  site-1  ', '  member-1  ', ['member-2', 'member-2']),
      new InterventionSchedule(InterventionPriority::LOW, new DateTimeImmutable('2026-06-15T08:00:00+00:00'), new DateTimeImmutable('2026-06-15T18:00:00+00:00')),
    ));

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
    Intervention::create(new InterventionCreation(
      'intervention-1',
      '   ',
      InterventionType::INVENTORY,
      new InterventionContent('Inventory', null),
      new InterventionOwnership(null, null, []),
      new InterventionSchedule(InterventionPriority::NORMAL, null, null),
    ));
  }

  #[Test]
  public function itRejectsANameLongerThan160CharactersOnCreate(): void
  {
    $this->expectException(InterventionConflictException::class);
    Intervention::create(new InterventionCreation(
      'intervention-1',
      'organization-1',
      InterventionType::INVENTORY,
      new InterventionContent(str_repeat('a', 161), null),
      new InterventionOwnership(null, null, []),
      new InterventionSchedule(InterventionPriority::NORMAL, null, null),
    ));
  }

  #[Test]
  public function itRejectsADueDateNotAfterPlannedStartOnCreate(): void
  {
    $this->expectException(InterventionConflictException::class);
    Intervention::create(new InterventionCreation(
      'intervention-1',
      'organization-1',
      InterventionType::INVENTORY,
      new InterventionContent('Inventory', null),
      new InterventionOwnership(null, null, []),
      new InterventionSchedule(InterventionPriority::NORMAL, new DateTimeImmutable('2026-06-15T18:00:00+00:00'), new DateTimeImmutable('2026-06-15T08:00:00+00:00')),
    ));
  }

  #[Test]
  public function itReconstitutesAnInterventionFromPersistedState(): void
  {
    $createdAt = new DateTimeImmutable('2026-06-01T00:00:00+00:00');
    $updatedAt = new DateTimeImmutable('2026-06-10T00:00:00+00:00');
    $plannedStartAt = new DateTimeImmutable('2026-06-15T08:00:00+00:00');
    $dueAt = new DateTimeImmutable('2026-06-15T18:00:00+00:00');

    $intervention = Intervention::reconstitute(new InterventionRestoredState(
      new InterventionCreation(
        'intervention-9',
        'organization-1',
        InterventionType::INSPECTION_CAMPAIGN,
        new InterventionContent('Annual campaign', 'Full sweep'),
        new InterventionOwnership('site-3', 'member-7', ['member-2', 'member-3']),
        new InterventionSchedule(InterventionPriority::HIGH, $plannedStartAt, $dueAt),
      ),
      InterventionStatus::SUBMITTED,
      'looks good',
      7,
      $createdAt,
      $updatedAt,
    ));

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

    $intervention->edit($policy, new InterventionPatch(
      ownership: new InterventionOwnershipChanges(siteId: '  site-2  ', responsibleId: null, participants: ['a', 'a', 'b'], hasSiteId: true, hasResponsibleId: true, hasParticipants: true),
      schedule: new InterventionScheduleChanges(priority: InterventionPriority::URGENT, hasPriority: true),
    ));

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
    $intervention->edit(new InterventionTransitionPolicy(), new InterventionPatch(
      schedule: new InterventionScheduleChanges(plannedStartAt: new DateTimeImmutable('2026-07-01T18:00:00+00:00'), dueAt: new DateTimeImmutable('2026-07-01T08:00:00+00:00'), hasPlannedStartAt: true, hasDueAt: true),
    ));
  }

  #[Test]
  public function itFreezesTheSiteAfterPlanning(): void
  {
    $policy = new InterventionTransitionPolicy();
    $intervention = $this->intervention();
    $intervention->transitionTo(InterventionStatus::PLANNED, $policy);

    $this->expectException(InterventionConflictException::class);
    $intervention->edit($policy, new InterventionPatch(
      ownership: new InterventionOwnershipChanges(siteId: 'site-2', hasSiteId: true),
    ));
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

    $intervention->edit(new InterventionTransitionPolicy(), new InterventionPatch(
      text: new InterventionTextChanges(name: '  New name  ', description: '  New description  ', reviewNote: '  a note  ', hasName: true, hasDescription: true, hasReviewNote: true),
      ownership: new InterventionOwnershipChanges(siteId: '  site-9  ', responsibleId: '  member-9  ', participants: ['a', 'a', 'b'], hasSiteId: true, hasResponsibleId: true, hasParticipants: true),
      schedule: new InterventionScheduleChanges(priority: InterventionPriority::URGENT, plannedStartAt: $plannedStartAt, dueAt: $dueAt, hasPriority: true, hasPlannedStartAt: true, hasDueAt: true),
      nextStatus: InterventionStatus::PLANNED,
    ));

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

    $intervention->edit(new InterventionTransitionPolicy(), new InterventionPatch(
      schedule: new InterventionScheduleChanges(priority: null, hasPriority: true),
    ));

    self::assertSame(InterventionPriority::NORMAL, $intervention->priority());
    self::assertSame(2, $intervention->revision());
  }

  #[Test]
  public function itRejectsEditingTheNameWithABlankValue(): void
  {
    $intervention = $this->intervention();

    $this->expectException(InterventionConflictException::class);
    $intervention->edit(new InterventionTransitionPolicy(), new InterventionPatch(
      text: new InterventionTextChanges(name: null, hasName: true),
    ));
  }

  #[Test]
  public function itRejectsEditingPlanningFieldsAfterPlanning(): void
  {
    $intervention = $this->intervention();
    $intervention->transitionTo(InterventionStatus::PLANNED, new InterventionTransitionPolicy());

    $this->expectException(InterventionConflictException::class);
    $intervention->edit(new InterventionTransitionPolicy(), new InterventionPatch(
      ownership: new InterventionOwnershipChanges(siteId: 'site-2', hasSiteId: true),
    ));
  }

  #[Test]
  public function itRejectsEditingAnImmutableIntervention(): void
  {
    $intervention = $this->published();

    $this->expectException(InterventionConflictException::class);
    $intervention->edit(new InterventionTransitionPolicy(), new InterventionPatch(
      text: new InterventionTextChanges(name: 'Renamed', hasName: true),
    ));
  }

  private function intervention(): Intervention
  {
    return Intervention::create(new InterventionCreation(
      'intervention-1',
      'organization-1',
      InterventionType::INVENTORY,
      new InterventionContent('Inventory', null),
      new InterventionOwnership('site-1', 'member-1', ['member-2']),
      new InterventionSchedule(InterventionPriority::NORMAL, new DateTimeImmutable('2026-06-15T08:00:00+00:00'), new DateTimeImmutable('2026-06-15T18:00:00+00:00')),
    ));
  }

  private function published(): Intervention
  {
    return Intervention::reconstitute(new InterventionRestoredState(
      new InterventionCreation(
        'intervention-9',
        'organization-1',
        InterventionType::INSPECTION_CAMPAIGN,
        new InterventionContent('Published campaign', 'Done'),
        new InterventionOwnership('site-1', 'member-1', ['member-2']),
        new InterventionSchedule(InterventionPriority::HIGH, new DateTimeImmutable('2026-06-15T08:00:00+00:00'), new DateTimeImmutable('2026-06-15T18:00:00+00:00')),
      ),
      InterventionStatus::PUBLISHED,
      'ok',
      7,
      new DateTimeImmutable('2026-06-01T00:00:00+00:00'),
      new DateTimeImmutable('2026-06-10T00:00:00+00:00'),
    ));
  }
}
