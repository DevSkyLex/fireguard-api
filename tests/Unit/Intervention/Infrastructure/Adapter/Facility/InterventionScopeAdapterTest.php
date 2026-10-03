<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Infrastructure\Adapter\Facility;

use DateTimeImmutable;
use Facility\Application\Contract\Hierarchy\InterventionParentAccess;
use Intervention\Application\Contract\Resource\InterventionAssignmentContext;
use Intervention\Application\Port\Outbound\InterventionResourceGatewayPort;
use Intervention\Application\Service\InterventionMemberPolicy;
use Intervention\Infrastructure\Adapter\Facility\InterventionScopeAdapter;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\Port\Outbound\OrganizationMemberRepositoryPort;
use Organization\Domain\Model\OrganizationMember\OrganizationMember;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationMemberId};
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test InterventionScopeAdapterTest.
 *
 * Preserves scope concealment and the distinction between planning entitlement and execution membership.
 *
 * @category Test
 */
#[CoversClass(InterventionScopeAdapter::class)]
final class InterventionScopeAdapterTest extends TestCase
{
  // #region Constants
  /**
   * Constant ORGANIZATION_ID.
   */
  private const string ORGANIZATION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63c11';

  /**
   * Constant INTERVENTION_ID.
   */
  private const string INTERVENTION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63c12';

  /**
   * Constant MEMBER_ID.
   */
  private const string MEMBER_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63c13';

  /**
   * Constant USER_ID.
   */
  private const string USER_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63c14';
  // #endregion

  // #region Methods
  /**
   * Method itConcealsMissingAndForeignInterventionsWithoutPermissionReads.
   *
   * @access public
   *
   * @param ?string $organizationId stored organization or an absent intervention
   *
   * @return void
   */
  #[Test]
  #[DataProvider('unreadableInterventions')]
  public function itConcealsMissingAndForeignInterventionsWithoutPermissionReads(?string $organizationId): void
  {
    $resources = $this->createStub(InterventionResourceGatewayPort::class);
    $context = null === $organizationId ? null : new InterventionAssignmentContext(self::INTERVENTION_ID, $organizationId, 'draft');
    $resources->method('interventionAssignmentContext')->willReturn($context);
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::never())->method('hasPermission');
    $adapter = new InterventionScopeAdapter($resources, $authorization, new InterventionMemberPolicy($this->createStub(OrganizationMemberRepositoryPort::class)));
    self::assertSame(InterventionParentAccess::NOT_FOUND, $adapter->preparationAccess(self::ORGANIZATION_ID, self::INTERVENTION_ID, self::USER_ID));
  }

  /**
   * Method unreadableInterventions.
   *
   * @access public
   *
   * @return iterable<string, array{?string}> absent and foreign organization contexts
   */
  public static function unreadableInterventions(): iterable
  {
    yield 'missing' => [null];
    yield 'foreign organization' => ['018f0b68-6758-7a12-8a1d-3f0d97f63c15'];
  }

  /**
   * Method itDeniesImmutableStatusesBeforePermissionReads.
   *
   * @access public
   *
   * @param string $status immutable or unsupported status
   *
   * @return void
   */
  #[Test]
  #[DataProvider('immutableStatuses')]
  public function itDeniesImmutableStatusesBeforePermissionReads(string $status): void
  {
    $resources = $this->createStub(InterventionResourceGatewayPort::class);
    $resources->method('interventionAssignmentContext')->willReturn(new InterventionAssignmentContext(self::INTERVENTION_ID, self::ORGANIZATION_ID, $status));
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::never())->method('hasPermission');
    $adapter = new InterventionScopeAdapter($resources, $authorization, new InterventionMemberPolicy($this->createStub(OrganizationMemberRepositoryPort::class)));
    self::assertSame(InterventionParentAccess::DENIED, $adapter->preparationAccess(self::ORGANIZATION_ID, self::INTERVENTION_ID, self::USER_ID));
  }

  /**
   * Method immutableStatuses.
   *
   * @access public
   *
   * @return iterable<string, array{string}> statuses outside the preparation workflow
   */
  public static function immutableStatuses(): iterable
  {
    yield 'submitted' => ['submitted'];
    yield 'published' => ['published'];
    yield 'abandoned' => ['abandoned'];
    yield 'unsupported' => ['unknown'];
  }

  /**
   * Method itAllowsDraftPlanningWithoutExecutionMembership.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function itAllowsDraftPlanningWithoutExecutionMembership(): void
  {
    $resources = $this->createStub(InterventionResourceGatewayPort::class);
    $resources->method('interventionAssignmentContext')->willReturn(new InterventionAssignmentContext(self::INTERVENTION_ID, self::ORGANIZATION_ID, 'draft'));
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())->method('hasPermission')->with(self::USER_ID, self::ORGANIZATION_ID, 'organization.interventions.plan')->willReturn(true);
    $members = $this->createMock(OrganizationMemberRepositoryPort::class);
    $members->expects(self::never())->method('findByOrganizationAndUser');
    $adapter = new InterventionScopeAdapter($resources, $authorization, new InterventionMemberPolicy($members));
    self::assertSame(InterventionParentAccess::GRANTED, $adapter->preparationAccess(self::ORGANIZATION_ID, self::INTERVENTION_ID, self::USER_ID));
  }

  /**
   * Method itDeniesExecutionWithoutEntitlementBeforeMembershipReads.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function itDeniesExecutionWithoutEntitlementBeforeMembershipReads(): void
  {
    $resources = $this->createStub(InterventionResourceGatewayPort::class);
    $resources->method('interventionAssignmentContext')->willReturn(new InterventionAssignmentContext(self::INTERVENTION_ID, self::ORGANIZATION_ID, 'planned', self::MEMBER_ID));
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())->method('hasPermission')->with(self::USER_ID, self::ORGANIZATION_ID, 'organization.interventions.execute')->willReturn(false);
    $members = $this->createMock(OrganizationMemberRepositoryPort::class);
    $members->expects(self::never())->method('findByOrganizationAndUser');
    $adapter = new InterventionScopeAdapter($resources, $authorization, new InterventionMemberPolicy($members));
    self::assertSame(InterventionParentAccess::DENIED, $adapter->preparationAccess(self::ORGANIZATION_ID, self::INTERVENTION_ID, self::USER_ID));
  }

  /**
   * Method itRequiresExecutionParticipationEvenWithEntitlement.
   *
   * @access public
   *
   * @param bool $participating whether the active member participates
   *
   * @return void
   */
  #[Test]
  #[DataProvider('executionParticipation')]
  public function itRequiresExecutionParticipationEvenWithEntitlement(bool $participating): void
  {
    $resources = $this->createStub(InterventionResourceGatewayPort::class);
    $resources->method('interventionAssignmentContext')->willReturn(new InterventionAssignmentContext(self::INTERVENTION_ID, self::ORGANIZATION_ID, 'in_progress', participants: $participating ? [self::MEMBER_ID] : []));
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);
    $member = OrganizationMember::reconstitute(OrganizationMemberId::fromString(self::MEMBER_ID), OrganizationId::fromString(self::ORGANIZATION_ID), self::USER_ID, true, new DateTimeImmutable());
    $members = $this->createMock(OrganizationMemberRepositoryPort::class);
    $members->expects(self::once())->method('findByOrganizationAndUser')->with(OrganizationId::fromString(self::ORGANIZATION_ID), self::USER_ID)->willReturn($member);
    $adapter = new InterventionScopeAdapter($resources, $authorization, new InterventionMemberPolicy($members));
    self::assertSame($participating ? InterventionParentAccess::GRANTED : InterventionParentAccess::DENIED, $adapter->preparationAccess(self::ORGANIZATION_ID, self::INTERVENTION_ID, self::USER_ID));
  }

  /**
   * Method executionParticipation.
   *
   * @access public
   *
   * @return iterable<string, array{bool}> participation entitlement cases
   */
  public static function executionParticipation(): iterable
  {
    yield 'participant' => [true];
    yield 'unassigned member' => [false];
  }
  // #endregion
}
