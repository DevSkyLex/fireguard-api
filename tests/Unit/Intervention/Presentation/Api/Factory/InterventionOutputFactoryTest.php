<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Presentation\Api\Factory;

use DateTimeImmutable;
use Intervention\Application\Contract\Workflow\InterventionWorkflowView;
use Intervention\Application\Service\{InterventionActionPolicy, InterventionMemberPolicy};
use Intervention\Domain\Service\{InterventionChangePolicy, InterventionMutabilityPolicy, InterventionTransitionPolicy};
use Intervention\Presentation\Api\Factory\InterventionOutputFactory;
use LogicException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\Port\Outbound\OrganizationMemberRepositoryPort;
use Organization\Domain\Model\OrganizationMember\OrganizationMember;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationMemberId};
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

    $output = new InterventionOutputFactory(new InterventionTransitionPolicy())->fromView($view);

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

    $output = new InterventionOutputFactory(new InterventionTransitionPolicy())->fromView($view);

    self::assertSame([], $output->labels);
  }

  #[Test]
  public function itOffersWithdrawalAlongsideChangesRequestedFromSubmitted(): void
  {
    $data = $this->baseData([]);
    $data['status'] = 'submitted';

    $output = new InterventionOutputFactory(new InterventionTransitionPolicy())
      ->fromView(new InterventionWorkflowView('intervention', 'organization-1', $data));

    self::assertSame(['changes_requested', 'in_progress'], $output->allowedTransitions);
  }

  #[Test]
  public function itOffersNoTransitionsWhenTheStoredStatusIsNotAKnownWorkflowStatus(): void
  {
    $data = $this->baseData([]);
    $data['status'] = 'archived_by_hand';

    $output = new InterventionOutputFactory(new InterventionTransitionPolicy())
      ->fromView(new InterventionWorkflowView('intervention', 'organization-1', $data));

    self::assertSame([], $output->allowedTransitions);
  }

  #[Test]
  public function itMapsTheRecurrenceOriginWhenTheViewCarriesOne(): void
  {
    $data = $this->baseData([]);
    $data['recurrence'] = '/api/intervention-recurrences/recurrence-1';

    $output = new InterventionOutputFactory(new InterventionTransitionPolicy())
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

    $output = new InterventionOutputFactory(new InterventionTransitionPolicy())
      ->fromView(new InterventionWorkflowView('intervention', 'organization-1', $data));

    self::assertNull($output->recurrence);
  }

  #[Test]
  public function itLeavesAllowedActionsAbsentOnTheCallerAgnosticFromView(): void
  {
    $output = new InterventionOutputFactory(new InterventionTransitionPolicy())
      ->fromView(new InterventionWorkflowView('intervention', 'organization-1', $this->baseData([])));

    self::assertNull($output->allowedActions);
  }

  #[Test]
  public function itPopulatesAllowedActionsFromTheSharedActionPolicyForTheCaller(): void
  {
    $data = $this->baseData([]);
    $data['status'] = 'draft';
    $view = new InterventionWorkflowView('intervention', self::ORGANIZATION_UUID, $data);

    $output = new InterventionOutputFactory(new InterventionTransitionPolicy(), $this->actionPolicy())
      ->fromViewForCaller($view, self::MEMBER_ID);

    self::assertNotNull($output->allowedActions);
    self::assertTrue($output->allowedActions->canEditDetails);
    self::assertTrue($output->allowedActions->canEditSite);
    self::assertTrue($output->allowedActions->canDelete);
    self::assertFalse($output->allowedActions->canPublish);
  }

  #[Test]
  public function itResolvesTheResponsibleAndParticipantIrisBackToRawMemberIdsForIdentityChecks(): void
  {
    $data = $this->baseData([]);
    $data['status'] = 'in_progress';
    $data['responsible'] = '/api/organizations/' . self::ORGANIZATION_UUID . '/members/' . self::MEMBER_ID;
    $view = new InterventionWorkflowView('intervention', self::ORGANIZATION_UUID, $data);

    $output = new InterventionOutputFactory(new InterventionTransitionPolicy(), $this->actionPolicy())
      ->fromViewForCaller($view, self::MEMBER_ID);

    self::assertNotNull($output->allowedActions);
    self::assertTrue($output->allowedActions->canSubmit);
  }

  #[Test]
  public function itRefusesToBuildTheCallerSpecificOutputWithoutAWiredActionPolicy(): void
  {
    $factory = new InterventionOutputFactory(new InterventionTransitionPolicy());

    $this->expectException(LogicException::class);

    $factory->fromViewForCaller(new InterventionWorkflowView('intervention', 'organization-1', $this->baseData([])), self::MEMBER_ID);
  }

  private function actionPolicy(): InterventionActionPolicy
  {
    $repository = $this->createStub(OrganizationMemberRepositoryPort::class);
    $repository->method('findByOrganizationAndUser')->willReturnCallback(
      static fn (OrganizationId $organizationId, string $userId): ?OrganizationMember => self::MEMBER_ID === $userId
        ? OrganizationMember::reconstitute(OrganizationMemberId::fromString(self::MEMBER_ID), $organizationId, $userId, true, new DateTimeImmutable())
        : null,
    );
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    return new InterventionActionPolicy(
      $authorization,
      new InterventionMemberPolicy($repository),
      new InterventionTransitionPolicy(),
      new InterventionMutabilityPolicy(),
      new InterventionChangePolicy(),
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
