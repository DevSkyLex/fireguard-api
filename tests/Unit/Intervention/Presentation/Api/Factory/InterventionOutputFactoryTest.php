<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Presentation\Api\Factory;

use Intervention\Application\Contract\Workflow\InterventionWorkflowView;
use Intervention\Presentation\Api\Factory\InterventionOutputFactory;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test InterventionOutputFactoryTest.
 *
 * @category Factory Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(InterventionOutputFactory::class)]
final class InterventionOutputFactoryTest extends TestCase
{
  private const string MEMBER_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63c13';

  private const string ORGANIZATION_UUID = '018f0b68-6758-7a12-8a1d-3f0d97f63c11';

  #[Test]
  public function itMapsEmbeddedLabelSummaries(): void
  {
    $view = new InterventionWorkflowView('intervention', 'organization-1', $this->baseData([
      ['id' => 'label-1', 'name' => 'Urgent', 'color' => '#ff0000'],
      ['id' => 'label-2', 'name' => 'Follow-up', 'color' => '#00ff00'],
    ]));

    $output = new InterventionOutputFactory()->fromView($view);

    self::assertSame(
      [
        ['id' => 'label-1', 'name' => 'Urgent', 'color' => '#ff0000'],
        ['id' => 'label-2', 'name' => 'Follow-up', 'color' => '#00ff00'],
      ],
      $output->labels,
    );
  }

  #[Test]
  public function itMapsAnEmptyLabelListWhenNoLabelsAreAssigned(): void
  {
    $view = new InterventionWorkflowView('intervention', 'organization-1', $this->baseData([]));

    $output = new InterventionOutputFactory()->fromView($view);

    self::assertSame([], $output->labels);
  }

  #[Test]
  public function itMapsTheAuthorizedTransitionSnapshot(): void
  {
    $data = $this->baseData([]);
    $data['status'] = 'submitted';
    $data['allowedTransitions'] = ['changes_requested', 'in_progress'];

    $output = new InterventionOutputFactory()
      ->fromView(new InterventionWorkflowView('intervention', 'organization-1', $data));

    self::assertSame(['changes_requested', 'in_progress'], $output->allowedTransitions);
  }

  #[Test]
  public function itOffersNoTransitionsWhenTheStoredStatusIsNotAKnownWorkflowStatus(): void
  {
    $data = $this->baseData([]);
    $data['status'] = 'archived_by_hand';

    $output = new InterventionOutputFactory()
      ->fromView(new InterventionWorkflowView('intervention', 'organization-1', $data));

    self::assertSame([], $output->allowedTransitions);
  }

  #[Test]
  public function itMapsTheRecurrenceOriginWhenTheViewCarriesOne(): void
  {
    $data = $this->baseData([]);
    $data['recurrence'] = '/api/intervention-recurrences/recurrence-1';

    $output = new InterventionOutputFactory()
      ->fromView(new InterventionWorkflowView('intervention', 'organization-1', $data));

    self::assertSame('/api/intervention-recurrences/recurrence-1', $output->recurrence);
  }

  #[Test]
  public function itLeavesTheRecurrenceNullWhenTheViewOmitsTheKeyEntirely(): void
  {
    // The list path never puts the key in the view data at all, and the field
    // must survive that rather than throwing on a missing key.
    $data = $this->baseData([]);
    self::assertArrayNotHasKey('recurrence', $data);

    $output = new InterventionOutputFactory()
      ->fromView(new InterventionWorkflowView('intervention', 'organization-1', $data));

    self::assertNull($output->recurrence);
  }

  #[Test]
  public function itLeavesAllowedActionsAbsentOnTheCallerAgnosticFromView(): void
  {
    $output = new InterventionOutputFactory()
      ->fromView(new InterventionWorkflowView('intervention', 'organization-1', $this->baseData([])));

    self::assertNull($output->allowedActions);
  }

  #[Test]
  public function itMapsTheAuthorizedActionSnapshotForTheCaller(): void
  {
    $data = $this->baseData([]);
    $data['status'] = 'draft';
    $data['allowedActions'] = $this->actions();
    $view = new InterventionWorkflowView('intervention', self::ORGANIZATION_UUID, $data);

    $output = new InterventionOutputFactory()
      ->fromViewForCaller($view, self::MEMBER_ID);

    self::assertNotNull($output->allowedActions);
    self::assertTrue($output->allowedActions->canEditDetails);
    self::assertTrue($output->allowedActions->canEditSite);
    self::assertTrue($output->allowedActions->canDelete);
    self::assertFalse($output->allowedActions->canPublish);
  }

  #[Test]
  public function itDoesNotPromoteADeniedActionWhenTheCallerMatchesTheResponsibleIri(): void
  {
    $data = $this->baseData([]);
    $data['status'] = 'in_progress';
    $data['responsible'] = '/api/organizations/' . self::ORGANIZATION_UUID . '/members/' . self::MEMBER_ID;
    $data['allowedActions'] = $this->actions(canSubmit: false);
    $view = new InterventionWorkflowView('intervention', self::ORGANIZATION_UUID, $data);

    $output = new InterventionOutputFactory()
      ->fromViewForCaller($view, self::MEMBER_ID);

    self::assertNotNull($output->allowedActions);
    self::assertFalse($output->allowedActions->canSubmit);
    self::assertSame($data['responsible'], $output->responsible);
  }

  #[Test]
  public function itLeavesActionsAbsentWhenTheApplicationSnapshotIsMissing(): void
  {
    $factory = new InterventionOutputFactory();

    $output = $factory->fromViewForCaller(new InterventionWorkflowView('intervention', 'organization-1', $this->baseData([])), self::MEMBER_ID);
    self::assertNull($output->allowedActions);
  }

  private function actions(bool $canSubmit = false): \Intervention\Application\Contract\Workflow\InterventionAllowedActions
  {
    return new \Intervention\Application\Contract\Workflow\InterventionAllowedActions(
      canEditDetails: true,
      canEditSite: true,
      canEditResponsible: true,
      canEditPlanning: true,
      canMutateWorkItems: true,
      canMutateChanges: true,
      canAssignTeam: true,
      canManageAttachments: true,
      canSubmit: $canSubmit,
      canWithdraw: false,
      canDelete: true,
      canPublish: false,
    );
  }

  /**
   * @param list<array{id: string, name: string, color: string}> $labels
   *
   * @return array<string, mixed>
   */
  private function baseData(array $labels): array
  {
    return [
      'id' => 'intervention-1',
      'organization' => '/api/organizations/organization-1',
      'number' => 1,
      'type' => 'site_setup',
      'name' => 'Mission',
      'description' => null,
      'status' => 'draft',
      'site' => null,
      'responsible' => null,
      'participants' => [],
      'priority' => 'normal',
      'plannedStartAt' => null,
      'dueAt' => null,
      'reviewNote' => null,
      'revision' => 1,
      'facilitiesCount' => 0,
      'equipmentCount' => 0,
      'inspectionsCount' => 0,
      'blockersCount' => 0,
      'workItemsCount' => 0,
      'completedWorkItemsCount' => 0,
      'proposedChangesCount' => 0,
      'commentsCount' => 0,
      'hasSignature' => false,
      'labels' => $labels,
      'createdAt' => '2026-07-09T13:00:00+00:00',
      'updatedAt' => '2026-07-09T13:00:00+00:00',
    ];
  }
}
