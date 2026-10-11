<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Application\Service;

use DateTimeImmutable;
use Intervention\Application\Contract\Time\TimeEntryTaskContext;
use Intervention\Application\Contract\Workflow\{InterventionAllowedActions, InterventionWorkflowContext, InterventionWorkflowView};
use Intervention\Application\Port\Outbound\Internal\InterventionTimeEntryRepositoryPort;
use Intervention\Application\Port\Outbound\{InterventionEquipmentSummaryPort, InterventionSiteNamingPort, InterventionWorkflowGatewayPort};
use Intervention\Application\Service\{InterventionActionPolicy, InterventionMemberPolicy, InterventionTimeAccessPolicy, InterventionWorkItemCapabilities, InterventionWorkItemEnrichment};
use Intervention\Domain\Service\{InterventionChangePolicy, InterventionMutabilityPolicy, InterventionTransitionPolicy};
use Organization\Application\Contract\Member\OrganizationMemberView;
use Organization\Application\Contract\Workforce\OrganizationWorkforceMember;
use Organization\Application\Port\Inbound\{OrganizationAuthorizationPort, OrganizationMemberDirectoryPort, OrganizationWorkforceDirectoryPort};
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use User\Application\Contract\User\UserView;
use User\Application\Port\Inbound\UserDirectoryPort;

/**
 * Tests grouped display projections and caller capabilities at the Application boundary.
 *
 * @category Test
 * @version 1.0.0
 */
#[CoversClass(InterventionWorkItemEnrichment::class)]
final class InterventionWorkItemEnrichmentTest extends TestCase
{
  #[Test]
  public function testOnePageBatchesRepeatedTargetsAndAccountsAndPreservesUnknownReferences(): void
  {
    $equipment = $this->createMock(InterventionEquipmentSummaryPort::class);
    $equipment->expects(self::once())->method('summaries')->with('org', ['equipment-1', 'missing-equipment'])
      ->willReturn(['equipment-1' => ['type' => 'Extinguisher', 'serialNumber' => 'SN-1']]);
    $sites = $this->createMock(InterventionSiteNamingPort::class);
    $sites->expects(self::once())->method('findNamesByIds')->with('org', ['facility-1'])->willReturn(['facility-1' => 'Warehouse']);
    $workforce = $this->createMock(OrganizationWorkforceDirectoryPort::class);
    $workforce->expects(self::once())->method('members')->with('org')->willReturn([
      new OrganizationWorkforceMember('member-1', 'user-1', true),
      new OrganizationWorkforceMember('member-2', 'user-1', true),
      new OrganizationWorkforceMember('member-3', 'unknown-user', true),
    ]);
    $users = $this->createMock(UserDirectoryPort::class);
    $users->expects(self::once())->method('findByIds')->with(['user-1', 'unknown-user'])->willReturn(['user-1' => $this->user()]);
    $users->expects(self::never())->method('findById');
    $views = [
      $this->workItem('task-1', '/api/equipment/equipment-1', 'member-1'),
      $this->workItem('task-2', '/api/equipment/equipment-1', 'member-2'),
      $this->workItem('task-3', '/api/facilities/facility-1', 'member-3'),
      $this->workItem('task-4', '/api/equipment/missing-equipment', 'missing-member'),
      $this->workItem('task-5', 'Free-text target', null),
    ];

    $result = $this->enrichment($equipment, $sites, $workforce, $users)->enrich($views, 'caller');

    self::assertCount(5, $result);
    self::assertSame(['resource' => '/api/equipment/equipment-1', 'kind' => 'equipment', 'label' => 'Extinguisher · SN-1'], $result[0]->data['targetSummary']);
    self::assertSame($result[0]->data['targetSummary'], $result[1]->data['targetSummary']);
    self::assertSame(['resource' => '/api/facilities/facility-1', 'kind' => 'facility', 'label' => 'Warehouse'], $result[2]->data['targetSummary']);
    self::assertSame([
      'member' => '/api/organizations/org/members/member-1', 'userId' => 'user-1',
      'displayName' => 'Ada Example', 'firstName' => 'Ada', 'lastName' => 'Example', 'avatarUrl' => 'avatar.png',
    ], $result[0]->data['assigneeProfile']);
    self::assertSame([
      'member' => '/api/organizations/org/members/member-3', 'userId' => 'unknown-user',
      'displayName' => 'unknown-user', 'firstName' => null, 'lastName' => null, 'avatarUrl' => null,
    ], $result[2]->data['assigneeProfile']);
    self::assertNull($result[3]->data['targetSummary']);
    self::assertNull($result[3]->data['assigneeProfile']);
    self::assertSame('/api/equipment/missing-equipment', $result[3]->data['target']);
    self::assertSame('/api/organizations/org/members/missing-member', $result[3]->data['assignee']);
    self::assertNull($result[4]->data['targetSummary']);
    self::assertSame('Free-text target', $result[4]->data['target']);
    self::assertArrayNotHasKey('assigneeProfile', $views[0]->data);
  }

  #[Test]
  public function testDisplayLookupFailuresPreserveTheAuthorizedWorkItems(): void
  {
    $equipment = $this->createMock(InterventionEquipmentSummaryPort::class);
    $equipment->expects(self::once())->method('summaries')->willThrowException(new RuntimeException('Display directory unavailable.'));
    $sites = $this->createMock(InterventionSiteNamingPort::class);
    $sites->expects(self::once())->method('findNamesByIds')->with('org', ['facility-1'])->willReturn(['facility-1' => 'Warehouse']);
    $workforce = $this->createStub(OrganizationWorkforceDirectoryPort::class);
    $workforce->method('members')->willReturn([new OrganizationWorkforceMember('member-1', 'user-1', true)]);
    $users = $this->createMock(UserDirectoryPort::class);
    $users->expects(self::once())->method('findByIds')->willThrowException(new RuntimeException('Account directory unavailable.'));

    $result = $this->enrichment($equipment, $sites, $workforce, $users)->enrich([$this->workItem('task-1', '/api/equipment/equipment-1', 'member-1'), $this->workItem('task-2', '/api/facilities/facility-1', null)], 'caller');

    self::assertCount(2, $result);
    self::assertSame('task-1', $result[0]->data['id']);
    self::assertSame(['resource' => '/api/facilities/facility-1', 'kind' => 'facility', 'label' => 'Warehouse'], $result[1]->data['targetSummary']);
    self::assertNull($result[0]->data['targetSummary']);
    self::assertSame('/api/equipment/equipment-1', $result[0]->data['target']);
    self::assertSame([
      'member' => '/api/organizations/org/members/member-1', 'userId' => 'user-1',
      'displayName' => 'user-1', 'firstName' => null, 'lastName' => null, 'avatarUrl' => null,
    ], $result[0]->data['assigneeProfile']);
  }

  #[Test]
  public function testTheSameTargetIdentifierIsResolvedWithinEachOwningOrganization(): void
  {
    $equipment = $this->createMock(InterventionEquipmentSummaryPort::class);
    $equipment->expects(self::exactly(2))->method('summaries')->willReturnCallback(static function (string $organizationId, array $ids): array {
      self::assertSame(['equipment-1'], $ids);

      return ['equipment-1' => ['type' => $organizationId, 'serialNumber' => null]];
    });
    $users = $this->createMock(UserDirectoryPort::class);
    $users->expects(self::never())->method('findByIds');
    $workforce = $this->createMock(OrganizationWorkforceDirectoryPort::class);
    $workforce->expects(self::exactly(2))->method('members')->willReturn([]);

    $result = $this->enrichment(equipment: $equipment, workforce: $workforce, users: $users)->enrich([
      $this->workItem('task-1', '/api/equipment/equipment-1', null, 'org-a'),
      $this->workItem('task-2', '/api/equipment/equipment-1', null, 'org-b'),
    ], 'caller');

    self::assertSame(['resource' => '/api/equipment/equipment-1', 'kind' => 'equipment', 'label' => 'org-a · equipment-1'], $result[0]->data['targetSummary']);
    self::assertSame(['resource' => '/api/equipment/equipment-1', 'kind' => 'equipment', 'label' => 'org-b · equipment-1'], $result[1]->data['targetSummary']);
  }

  #[Test]
  public function testWorkItemCapabilitiesAreGroupedAndRecomputedWhenPermissionsChange(): void
  {
    $tasks = $this->createMock(InterventionTimeEntryRepositoryPort::class);
    $tasks->expects(self::exactly(2))->method('contexts')->with(['task-1', 'task-2'])->willReturn([
      'task-1' => new TimeEntryTaskContext('task-1', 'parent', 'org', 'member-1', 'member-1', [], []),
      'task-2' => new TimeEntryTaskContext('task-2', 'parent', 'org', 'member-2', 'member-1', [], []),
    ]);
    $tasks->expects(self::never())->method('context');
    $workflow = $this->createMock(InterventionWorkflowGatewayPort::class);
    $workflow->expects(self::exactly(2))->method('interventionContext')->with('parent')->willReturn(new InterventionWorkflowContext('parent', 'org', 'planned', 'member-1'));
    $workforce = $this->createMock(OrganizationWorkforceDirectoryPort::class);
    $workforce->expects(self::exactly(4))->method('members')->with('org')->willReturn([new OrganizationWorkforceMember('member-1', 'caller', true)]);
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::exactly(2))->method('isMemberOf')->with('caller', 'org')->willReturn(true);
    $authorization->expects(self::exactly(8))->method('hasPermission')->with('caller', 'org', self::anything())
      ->willReturnOnConsecutiveCalls(false, true, true, true, false, false, false, false);
    $enrichment = $this->enrichment(workforce: $workforce, authorization: $authorization, tasks: $tasks, workflow: $workflow);
    $views = [$this->workItem('task-1', null, null), $this->workItem('task-2', null, null)];
    $first = $enrichment->enrich($views, 'caller');
    $second = $enrichment->enrich($views, 'caller');

    self::assertSame(['canLogTime' => true, 'canManageTime' => false, 'canReestimate' => true, 'canReassign' => true, 'canEditPlanning' => true, 'canExecute' => true], $first[0]->data['allowedActions']);
    self::assertSame(['canLogTime' => false, 'canManageTime' => false, 'canReestimate' => false, 'canReassign' => true, 'canEditPlanning' => true, 'canExecute' => false], $first[1]->data['allowedActions']);
    $denied = ['canLogTime' => false, 'canManageTime' => false, 'canReestimate' => false, 'canReassign' => false, 'canEditPlanning' => false, 'canExecute' => false];
    self::assertSame($denied, $second[0]->data['allowedActions']);
    self::assertSame($denied, $second[1]->data['allowedActions']);
  }

  #[Test]
  public function testInterventionCapabilitiesUseOneMembershipLookupPerOrganizationAndRefreshForTheNextCaller(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturnCallback(static fn (string $userId, string $organizationId, string $permission): bool => 'responsible' === $userId && 'organization.interventions.execute' === $permission);
    $members = $this->createMock(OrganizationMemberDirectoryPort::class);
    $members->expects(self::exactly(2))->method('findByOrganizationAndUser')->with('org', self::anything())
      ->willReturnCallback(static fn (string $organizationId, string $userId): OrganizationMemberView => new OrganizationMemberView('responsible' === $userId ? 'member-1' : 'member-2', $organizationId, $userId, true));
    $enrichment = $this->enrichment(authorization: $authorization, members: $members);
    $view = new InterventionWorkflowView('intervention', 'org', ['id' => 'parent-1', 'status' => 'in_progress', 'responsible' => '/api/organizations/org/members/member-1', 'participants' => []]);
    $views = [$view, new InterventionWorkflowView('intervention', 'org', [...$view->data, 'id' => 'parent-2'])];

    $allowed = $enrichment->enrich($views, 'responsible');
    $denied = $enrichment->enrich($views, 'reader');
    $allowedActions = new InterventionAllowedActions(
      canEditDetails: true,
      canEditSite: false,
      canEditResponsible: false,
      canEditPlanning: false,
      canMutateWorkItems: true,
      canMutateChanges: true,
      canAssignTeam: false,
      canManageAttachments: true,
      canSubmit: true,
      canWithdraw: false,
      canDelete: false,
      canPublish: false,
    );
    $deniedActions = new InterventionAllowedActions(
      canEditDetails: false,
      canEditSite: false,
      canEditResponsible: false,
      canEditPlanning: false,
      canMutateWorkItems: false,
      canMutateChanges: false,
      canAssignTeam: false,
      canManageAttachments: false,
      canSubmit: false,
      canWithdraw: false,
      canDelete: false,
      canPublish: false,
    );
    foreach ([0, 1] as $index) {
      self::assertSame(['submitted', 'abandoned'], $allowed[$index]->data['allowedTransitions']);
      self::assertEquals($allowedActions, $allowed[$index]->data['allowedActions']);
      self::assertEquals($deniedActions, $denied[$index]->data['allowedActions']);
      self::assertArrayNotHasKey('allowedActions', $views[$index]->data);
    }
  }

  #[Test]
  public function testMixedPagesKeepOriginalOrderAndMalformedHistoricalReferencesRemainReadable(): void
  {
    $equipment = $this->createMock(InterventionEquipmentSummaryPort::class);
    $equipment->expects(self::never())->method('summaries');
    $sites = $this->createMock(InterventionSiteNamingPort::class);
    $sites->expects(self::never())->method('findNamesByIds');
    $workforce = $this->createMock(OrganizationWorkforceDirectoryPort::class);
    $workforce->expects(self::exactly(2))->method('members')->willReturn([]);
    $users = $this->createMock(UserDirectoryPort::class);
    $users->expects(self::never())->method('findByIds');
    $views = [
      $this->workItem('first', '/api/equipment/', 'member/bad', 'org-a'),
      new InterventionWorkflowView('change', 'org-a', ['id' => 'change', 'status' => 'pending']),
      new InterventionWorkflowView('intervention', 'org-a', ['id' => 'parent', 'status' => 42, 'participants' => []]),
      $this->workItem('second', '/api/facilities/malformed/path', null, 'org-b'),
      new InterventionWorkflowView('work_item', 'org-a', ['id' => 'third', 'target' => ['retained' => 'legacy'], 'assignee' => ['legacy']]),
    ];

    $result = $this->enrichment($equipment, $sites, $workforce, $users)->enrich($views, 'caller');

    $ids = [];
    foreach ($result as $view) {
      $ids[] = $view->data['id'];
    }
    self::assertSame(['first', 'change', 'parent', 'second', 'third'], $ids);
    self::assertSame($views[1], $result[1]);
    self::assertSame([...$views[2]->data, 'allowedTransitions' => []], $result[2]->data);
    foreach ([0, 3, 4] as $index) {
      self::assertSame([...$views[$index]->data, 'targetSummary' => null, 'assigneeProfile' => null, 'allowedActions' => []], $result[$index]->data);
      self::assertArrayNotHasKey('targetSummary', $views[$index]->data);
    }
  }

  #[Test]
  #[DataProvider('displayNameFallbackCases')]
  public function testProfileNamesPreserveTheNameUsernameAndIdentityFallback(string $firstName, string $lastName, string $username, string $expected): void
  {
    $workforce = $this->createStub(OrganizationWorkforceDirectoryPort::class);
    $workforce->method('members')->willReturn([new OrganizationWorkforceMember('member-1', 'user-1', true)]);
    $users = $this->createMock(UserDirectoryPort::class);
    $users->expects(self::once())->method('findByIds')->with(['user-1'])->willReturn(['user-1' => $this->user($firstName, $lastName, $username)]);

    $result = $this->enrichment(workforce: $workforce, users: $users)->enrich([$this->workItem('task', null, 'member-1')], 'caller');

    self::assertSame([
      'member' => '/api/organizations/org/members/member-1', 'userId' => 'user-1',
      'displayName' => $expected, 'firstName' => $firstName, 'lastName' => $lastName, 'avatarUrl' => 'avatar.png',
    ], $result[0]->data['assigneeProfile']);
  }

  /**
   * @return iterable<string, array{string, string, string, string}>
   */
  public static function displayNameFallbackCases(): iterable
  {
    yield 'trimmed personal name wins' => [' Ada ', ' Example ', 'username', 'Ada   Example'];
    yield 'empty name uses username' => ['', '', 'username', 'username'];
    yield 'whitespace with empty username uses identity' => [' ', ' ', '', 'user-1'];
    yield 'retained zero name preserves PHP fallback' => ['0', '', 'username', 'username'];
    yield 'retained zero username uses identity' => ['', '', '0', 'user-1'];
  }

  #[Test]
  public function testWorkforceFailurePreservesTargetDisplaysWithoutResolvingAccounts(): void
  {
    $sites = $this->createMock(InterventionSiteNamingPort::class);
    $sites->expects(self::once())->method('findNamesByIds')->with('org', ['facility-1'])->willReturn(['facility-1' => 'Warehouse']);
    $workforce = $this->createMock(OrganizationWorkforceDirectoryPort::class);
    $workforce->expects(self::once())->method('members')->with('org')->willThrowException(new RuntimeException('Workforce directory unavailable.'));
    $users = $this->createMock(UserDirectoryPort::class);
    $users->expects(self::never())->method('findByIds');

    $result = $this->enrichment(sites: $sites, workforce: $workforce, users: $users)->enrich([$this->workItem('task', '/api/facilities/facility-1', 'member-1')], 'caller');

    self::assertSame(['resource' => '/api/facilities/facility-1', 'kind' => 'facility', 'label' => 'Warehouse'], $result[0]->data['targetSummary']);
    self::assertNull($result[0]->data['assigneeProfile']);
    self::assertSame('/api/organizations/org/members/member-1', $result[0]->data['assignee']);
  }

  private function workItem(string $id, ?string $target, ?string $memberId, string $organizationId = 'org'): InterventionWorkflowView
  {
    return new InterventionWorkflowView('work_item', $organizationId, ['id' => $id, 'status' => 'pending', 'target' => $target, 'assignee' => null === $memberId ? null : '/api/organizations/' . $organizationId . '/members/' . $memberId]);
  }

  private function user(string $firstName = 'Ada', string $lastName = 'Example', string $username = 'ada'): UserView
  {
    return new UserView('user-1', $username, 'ada@example.com', $firstName, $lastName, 'avatar.png', 'active', true, null, new DateTimeImmutable('2026-10-11T12:00:00+00:00'), null, true);
  }

  private function enrichment(
    ?InterventionEquipmentSummaryPort $equipment = null,
    ?InterventionSiteNamingPort $sites = null,
    ?OrganizationWorkforceDirectoryPort $workforce = null,
    ?UserDirectoryPort $users = null,
    ?OrganizationAuthorizationPort $authorization = null,
    ?InterventionTimeEntryRepositoryPort $tasks = null,
    ?InterventionWorkflowGatewayPort $workflow = null,
    ?OrganizationMemberDirectoryPort $members = null,
  ): InterventionWorkItemEnrichment {
    $authorization ??= $this->createStub(OrganizationAuthorizationPort::class);
    $workforce ??= $this->createStub(OrganizationWorkforceDirectoryPort::class);
    $transitions = new InterventionTransitionPolicy();
    $capabilities = new InterventionWorkItemCapabilities($tasks ?? $this->createStub(InterventionTimeEntryRepositoryPort::class), $workflow ?? $this->createStub(InterventionWorkflowGatewayPort::class), new InterventionTimeAccessPolicy($authorization, $workforce), $authorization, $workforce);
    $actions = new InterventionActionPolicy($authorization, new InterventionMemberPolicy($members ?? $this->createStub(OrganizationMemberDirectoryPort::class)), $transitions, new InterventionMutabilityPolicy(), new InterventionChangePolicy());

    return new InterventionWorkItemEnrichment($equipment ?? $this->createStub(InterventionEquipmentSummaryPort::class), $sites ?? $this->createStub(InterventionSiteNamingPort::class), $workforce, $users ?? $this->createStub(UserDirectoryPort::class), $capabilities, $actions, $transitions);
  }
}
