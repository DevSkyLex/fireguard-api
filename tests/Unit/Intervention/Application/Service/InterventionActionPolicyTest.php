<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Application\Service;

use DateTimeImmutable;
use Intervention\Application\Contract\Workflow\InterventionWorkflowContext;
use Intervention\Application\Service\{InterventionActionPolicy, InterventionAllowedActions, InterventionMemberPolicy};
use Intervention\Domain\Service\{InterventionChangePolicy, InterventionMutabilityPolicy, InterventionTransitionPolicy};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\Port\Outbound\OrganizationMemberRepositoryPort;
use Organization\Domain\Model\OrganizationMember\OrganizationMember;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationMemberId};
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use PHPUnit\Framework\TestCase;

use function in_array;

/**
 * Test InterventionActionPolicyTest.
 *
 * Covers {@see InterventionActionPolicy::allowedActions()} across the
 * matrix — each flag by status, by granted-permission set and by caller
 * identity — and {@see InterventionActionPolicy::requiredPermissions()},
 * the permission matrix moved out of `MutateInterventionWorkflowHandler`,
 * whose own unit test ({@see \Tests\Unit\Intervention\Application\UseCase\Workflow\InterventionWorkflowHandlersTest})
 * proves the handler still consults it.
 *
 * @category Application Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InterventionActionPolicyTest extends TestCase
{
  private const string ORGANIZATION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63c11';

  private const string RESPONSIBLE_MEMBER_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63c13';

  private const string PARTICIPANT_MEMBER_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63c15';

  private const string OUTSIDER_MEMBER_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63c16';

  // #region requiredPermissions()
  /**
   * @return iterable<string, array{string, string, array<string, mixed>, string, list<string>}>
   */
  public static function requiredPermissionsProvider(): iterable
  {
    yield 'creating an intervention' => ['intervention', 'create', ['organizationId' => 'x'], 'draft', ['organization.interventions.plan']];
    yield 'planning a draft' => ['intervention', 'update', ['status' => 'planned'], 'draft', ['organization.interventions.plan']];
    yield 'submitting for review' => ['intervention', 'update', ['status' => 'submitted'], 'planned', ['organization.interventions.execute']];
    yield 'requesting changes' => ['intervention', 'update', ['status' => 'changes_requested'], 'submitted', ['organization.interventions.review']];
    yield 'a work item on a draft' => ['work_item', 'create', [], 'draft', ['organization.interventions.plan']];
    yield 'a work item on a planned intervention' => ['work_item', 'update', [], 'planned', ['organization.interventions.execute']];
    yield 'a change on a submitted intervention' => ['change', 'update', [], 'submitted', ['organization.interventions.review']];
    yield 'a change on an in-progress intervention' => ['change', 'create', [], 'in_progress', ['organization.interventions.execute']];
    yield 'a pure replan on a planned intervention needs plan alone' => [
      'intervention', 'update', ['dueAt' => 'x', 'priority' => 'urgent'], 'planned', ['organization.interventions.plan'],
    ];
    yield 'a replan mixed with a transition needs both' => [
      'intervention', 'update', ['dueAt' => 'x', 'status' => 'in_progress'], 'planned',
      ['organization.interventions.execute', 'organization.interventions.plan'],
    ];
  }

  /**
   * @param array<string, mixed> $payload
   * @param list<string> $expected
   */
  #[Test]
  #[DataProvider('requiredPermissionsProvider')]
  public function itResolvesTheRequiredPermissionsForAMutation(
    string $resource,
    string $action,
    array $payload,
    string $contextStatus,
    array $expected,
  ): void {
    self::assertSame($expected, $this->policy()->requiredPermissions($resource, $action, $payload, $contextStatus));
  }
  // #endregion

  // #region allowedActions() — the happy path across every status, as the responsible member
  /**
   * @return iterable<string, array{string, InterventionAllowedActions}>
   */
  public static function allowedActionsAsResponsibleProvider(): iterable
  {
    yield 'draft' => ['draft', new InterventionAllowedActions(
      canEditDetails: true,
      canEditSite: true,
      canEditResponsible: true,
      canEditPlanning: true,
      canMutateWorkItems: true,
      canMutateChanges: false,
      canAssignTeam: true,
      canManageAttachments: true,
      canSubmit: false,
      canWithdraw: false,
      canDelete: true,
      canPublish: false,
    )];
    yield 'planned' => ['planned', new InterventionAllowedActions(
      canEditDetails: true,
      canEditSite: false,
      canEditResponsible: true,
      canEditPlanning: true,
      canMutateWorkItems: true,
      canMutateChanges: false,
      canAssignTeam: true,
      canManageAttachments: true,
      canSubmit: false,
      canWithdraw: false,
      canDelete: false,
      canPublish: false,
    )];
    yield 'in_progress' => ['in_progress', new InterventionAllowedActions(
      canEditDetails: true,
      canEditSite: false,
      canEditResponsible: false,
      canEditPlanning: true,
      canMutateWorkItems: true,
      canMutateChanges: true,
      canAssignTeam: true,
      canManageAttachments: true,
      canSubmit: true,
      canWithdraw: false,
      canDelete: false,
      canPublish: false,
    )];
    yield 'submitted' => ['submitted', new InterventionAllowedActions(
      canEditDetails: true,
      canEditSite: false,
      canEditResponsible: false,
      canEditPlanning: false,
      canMutateWorkItems: false,
      canMutateChanges: false,
      canAssignTeam: false,
      canManageAttachments: false,
      canSubmit: false,
      canWithdraw: true,
      canDelete: false,
      canPublish: true,
    )];
    yield 'changes_requested' => ['changes_requested', new InterventionAllowedActions(
      canEditDetails: true,
      canEditSite: false,
      canEditResponsible: false,
      canEditPlanning: true,
      canMutateWorkItems: true,
      canMutateChanges: true,
      canAssignTeam: true,
      canManageAttachments: true,
      canSubmit: true,
      canWithdraw: false,
      canDelete: false,
      canPublish: false,
    )];
    yield 'published' => ['published', new InterventionAllowedActions(
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
    )];
    yield 'abandoned' => ['abandoned', new InterventionAllowedActions(
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
      canDelete: true,
      canPublish: false,
    )];
  }

  #[Test]
  #[DataProvider('allowedActionsAsResponsibleProvider')]
  public function itComputesEveryFlagForTheResponsibleMemberWithFullPermissions(string $status, InterventionAllowedActions $expected): void
  {
    $policy = $this->policy($this->allGrantedAuthorization());
    $context = $this->context($status, self::RESPONSIBLE_MEMBER_ID, []);

    $actual = $policy->allowedActions($context, self::RESPONSIBLE_MEMBER_ID);

    self::assertEquals($expected, $actual);
  }
  // #endregion

  // #region Permission gating
  #[Test]
  public function itDeniesEveryFlagWhenNoPermissionIsGranted(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(false);
    $policy = $this->policy($authorization);
    $context = $this->context('in_progress', self::RESPONSIBLE_MEMBER_ID, []);

    $actual = $policy->allowedActions($context, self::RESPONSIBLE_MEMBER_ID);

    self::assertFalse($actual->canEditDetails);
    self::assertFalse($actual->canEditPlanning);
    self::assertFalse($actual->canMutateWorkItems);
    self::assertFalse($actual->canMutateChanges);
    self::assertFalse($actual->canAssignTeam);
    self::assertFalse($actual->canManageAttachments);
    self::assertFalse($actual->canSubmit);
    self::assertFalse($actual->canDelete);
    self::assertFalse($actual->canPublish);
  }
  // #endregion

  // #region Identity gating
  #[Test]
  public function itLetsAParticipantMutateWorkItemsAndAttachmentsOutsideDraft(): void
  {
    $policy = $this->policy($this->allGrantedAuthorization());
    $context = $this->context('in_progress', self::RESPONSIBLE_MEMBER_ID, [self::PARTICIPANT_MEMBER_ID]);

    $actual = $policy->allowedActions($context, self::PARTICIPANT_MEMBER_ID);

    self::assertTrue($actual->canMutateWorkItems);
    self::assertTrue($actual->canManageAttachments);
    self::assertTrue($actual->canMutateChanges);
  }

  #[Test]
  public function itDeniesAnOutsiderTheIdentityGatedFlagsOutsideDraft(): void
  {
    $policy = $this->policy($this->allGrantedAuthorization());
    $context = $this->context('in_progress', self::RESPONSIBLE_MEMBER_ID, [self::PARTICIPANT_MEMBER_ID]);

    $actual = $policy->allowedActions($context, self::OUTSIDER_MEMBER_ID);

    self::assertFalse($actual->canMutateWorkItems);
    self::assertFalse($actual->canManageAttachments);
    self::assertFalse($actual->canMutateChanges);
    self::assertFalse($actual->canSubmit);
    self::assertFalse($actual->canWithdraw);
  }

  #[Test]
  public function itDeniesSubmitAndWithdrawToAParticipantWhoIsNotResponsible(): void
  {
    $policy = $this->policy($this->allGrantedAuthorization());

    $submitContext = $this->context('in_progress', self::RESPONSIBLE_MEMBER_ID, [self::PARTICIPANT_MEMBER_ID]);
    self::assertFalse($policy->allowedActions($submitContext, self::PARTICIPANT_MEMBER_ID)->canSubmit);

    $withdrawContext = $this->context('submitted', self::RESPONSIBLE_MEMBER_ID, [self::PARTICIPANT_MEMBER_ID]);
    self::assertFalse($policy->allowedActions($withdrawContext, self::PARTICIPANT_MEMBER_ID)->canWithdraw);
  }

  #[Test]
  public function itAllowsADraftMutationByAnyoneWithThePlanPermissionRegardlessOfIdentity(): void
  {
    $policy = $this->policy($this->allGrantedAuthorization());
    $context = $this->context('draft', null, []);

    $actual = $policy->allowedActions($context, self::OUTSIDER_MEMBER_ID);

    self::assertTrue($actual->canMutateWorkItems);
    self::assertTrue($actual->canManageAttachments);
  }
  // #endregion

  #[Test]
  public function itDeniesEveryFlagForAnUnknownStatus(): void
  {
    $policy = $this->policy($this->allGrantedAuthorization());
    $context = $this->context('archived_by_hand', self::RESPONSIBLE_MEMBER_ID, []);

    $actual = $policy->allowedActions($context, self::RESPONSIBLE_MEMBER_ID);

    self::assertEquals(new InterventionAllowedActions(
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
    ), $actual);
  }

  private function policy(?OrganizationAuthorizationPort $authorization = null): InterventionActionPolicy
  {
    return new InterventionActionPolicy(
      $authorization ?? $this->createStub(OrganizationAuthorizationPort::class),
      new InterventionMemberPolicy($this->members()),
      new InterventionTransitionPolicy(),
      new InterventionMutabilityPolicy(),
      new InterventionChangePolicy(),
    );
  }

  private function allGrantedAuthorization(): OrganizationAuthorizationPort
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    return $authorization;
  }

  /**
   * @param list<string> $participants
   */
  private function context(string $status, ?string $responsibleId, array $participants): InterventionWorkflowContext
  {
    return new InterventionWorkflowContext(
      interventionId: 'intervention-1',
      organizationId: self::ORGANIZATION_ID,
      status: $status,
      responsibleId: $responsibleId,
      participants: $participants,
    );
  }

  /**
   * Resolves the caller's user id straight to a member of the same id — this
   * fixture treats "user id" and "organization member id" as the same
   * opaque string, which is enough to exercise identity gating without a
   * second layer of ids. {@see self::OUTSIDER_MEMBER_ID} is deliberately
   * still a real active member: an outsider to THIS intervention, not a
   * non-member of the organization.
   */
  private function members(): OrganizationMemberRepositoryPort
  {
    $repository = $this->createStub(OrganizationMemberRepositoryPort::class);
    $repository->method('findByOrganizationAndUser')->willReturnCallback(
      function (OrganizationId $organizationId, string $userId): ?OrganizationMember {
        if (!in_array($userId, [self::RESPONSIBLE_MEMBER_ID, self::PARTICIPANT_MEMBER_ID, self::OUTSIDER_MEMBER_ID], true)) {
          return null;
        }

        return OrganizationMember::reconstitute(
          OrganizationMemberId::fromString($userId),
          $organizationId,
          $userId,
          true,
          new DateTimeImmutable(),
        );
      },
    );

    return $repository;
  }
}
