<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Application\Service;

use DateTimeImmutable;
use Intervention\Application\Contract\Resource\InterventionAssignmentContext;
use Intervention\Application\Port\Outbound\InterventionResourceGatewayPort;
use Intervention\Application\Service\{InterventionMemberPolicy, InterventionResourceManager};
use Intervention\Domain\Exception\{ClientResourceAlreadyExistsException, InterventionConflictException};
use Intervention\Domain\ValueObject\InterventionResourceType;
use Organization\Application\Port\Outbound\OrganizationMemberRepositoryPort;
use Organization\Domain\Model\OrganizationMember\OrganizationMember;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationMemberId};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

#[CoversClass(InterventionResourceManager::class)]
final class InterventionResourceManagerTest extends TestCase
{
  private const INTERVENTION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63c10';

  private const ORGANIZATION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63c11';

  private const USER_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63c12';

  private const MEMBER_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63c13';

  #[Test]
  public function testRejectsAnAlreadySynchronizedOfflineCreation(): void
  {
    $resources = $this->createMock(InterventionResourceGatewayPort::class);
    $resources->expects(self::once())->method('clientIdExists')
      ->with(InterventionResourceType::EQUIPMENT, '550e8400-e29b-41d4-a716-446655440001')
      ->willReturn(true);

    $manager = new InterventionResourceManager($resources);

    $this->expectException(ClientResourceAlreadyExistsException::class);
    $this->expectExceptionMessage('A resource with this client identifier already exists.');

    $manager->assertOfflineCreate(
      InterventionResourceType::EQUIPMENT,
      '550e8400-e29b-41d4-a716-446655440001',
    );
  }

  #[Test]
  public function testRequiresPlanningPermissionForDraftInterventionResources(): void
  {
    self::assertSame(
      'organization.interventions.plan',
      $this->managerWithInterventionStatus('draft')->mutationPermission(
        self::INTERVENTION_ID,
      ),
    );
  }

  #[Test]
  public function testRequiresExecutionPermissionForPlannedInterventionResources(): void
  {
    self::assertSame(
      'organization.interventions.execute',
      $this->managerWithInterventionStatus('in_progress')->mutationPermission(
        self::INTERVENTION_ID,
      ),
    );
  }

  #[Test]
  public function testRejectsMutationAfterInterventionSubmission(): void
  {
    $this->expectException(InterventionConflictException::class);
    $this->expectExceptionMessage('Intervention resources are immutable in the current state.');

    $this->managerWithInterventionStatus('submitted')->mutationPermission(
      self::INTERVENTION_ID,
    );
  }

  #[Test]
  public function testChecksInterventionMembershipBeforeExecutionMutation(): void
  {
    $resources = $this->createMock(InterventionResourceGatewayPort::class);
    $resources->expects(self::once())->method('interventionMutationContext')
      ->with(self::INTERVENTION_ID)
      ->willReturn(new InterventionAssignmentContext(
        self::INTERVENTION_ID,
        self::ORGANIZATION_ID,
        'in_progress',
        'responsible-id',
        [self::MEMBER_ID],
      ));
    $members = $this->createMock(OrganizationMemberRepositoryPort::class);
    $members->expects(self::once())->method('findByOrganizationAndUser')
      ->with(OrganizationId::fromString(self::ORGANIZATION_ID), self::USER_ID)
      ->willReturn(OrganizationMember::reconstitute(
        OrganizationMemberId::fromString(self::MEMBER_ID),
        OrganizationId::fromString(self::ORGANIZATION_ID),
        self::USER_ID,
        true,
        new DateTimeImmutable(),
      ));

    $manager = new InterventionResourceManager($resources, new InterventionMemberPolicy($members));

    self::assertSame(
      'organization.interventions.execute',
      $manager->mutationPermission(self::INTERVENTION_ID, self::USER_ID),
    );
  }

  private function managerWithInterventionStatus(string $status): InterventionResourceManager
  {
    $resources = $this->createMock(InterventionResourceGatewayPort::class);
    $resources->expects(self::once())->method('interventionMutationContext')
      ->with(self::INTERVENTION_ID)
      ->willReturn(new InterventionAssignmentContext(self::INTERVENTION_ID, self::ORGANIZATION_ID, $status));

    return new InterventionResourceManager($resources);
  }
}
