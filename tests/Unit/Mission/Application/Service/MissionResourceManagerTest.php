<?php

declare(strict_types=1);

namespace Tests\Unit\Mission\Application\Service;

use DateTimeImmutable;
use Mission\Application\Contract\Resource\MissionAssignmentContext;
use Mission\Application\Port\Outbound\MissionResourceGatewayPort;
use Mission\Application\Service\{MissionMemberPolicy, MissionResourceManager};
use Mission\Domain\Exception\{ClientResourceAlreadyExistsException, MissionConflictException};
use Mission\Domain\ValueObject\MissionResourceType;
use Organization\Application\Port\Outbound\OrganizationMemberRepositoryPort;
use Organization\Domain\Model\OrganizationMember\OrganizationMember;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationMemberId};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

#[CoversClass(MissionResourceManager::class)]
final class MissionResourceManagerTest extends TestCase
{
  private const MISSION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63c10';

  private const ORGANIZATION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63c11';

  private const USER_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63c12';

  private const MEMBER_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63c13';

  #[Test]
  public function testRejectsAnAlreadySynchronizedOfflineCreation(): void
  {
    $resources = $this->createMock(MissionResourceGatewayPort::class);
    $resources->expects(self::once())->method('clientIdExists')
      ->with(MissionResourceType::EQUIPMENT, '550e8400-e29b-41d4-a716-446655440001')
      ->willReturn(true);

    $manager = new MissionResourceManager($resources);

    $this->expectException(ClientResourceAlreadyExistsException::class);
    $this->expectExceptionMessage('A resource with this client identifier already exists.');

    $manager->assertOfflineCreate(
      MissionResourceType::EQUIPMENT,
      '550e8400-e29b-41d4-a716-446655440001',
    );
  }

  #[Test]
  public function testRequiresPlanningPermissionForDraftMissionResources(): void
  {
    self::assertSame(
      'organization.missions.plan',
      $this->managerWithMissionStatus('draft')->mutationPermission(
        self::MISSION_ID,
      ),
    );
  }

  #[Test]
  public function testRequiresExecutionPermissionForPlannedMissionResources(): void
  {
    self::assertSame(
      'organization.missions.execute',
      $this->managerWithMissionStatus('in_progress')->mutationPermission(
        self::MISSION_ID,
      ),
    );
  }

  #[Test]
  public function testRejectsMutationAfterMissionSubmission(): void
  {
    $this->expectException(MissionConflictException::class);
    $this->expectExceptionMessage('Mission resources are immutable in the current state.');

    $this->managerWithMissionStatus('submitted')->mutationPermission(
      self::MISSION_ID,
    );
  }

  #[Test]
  public function testChecksMissionMembershipBeforeExecutionMutation(): void
  {
    $resources = $this->createMock(MissionResourceGatewayPort::class);
    $resources->expects(self::once())->method('missionMutationContext')
      ->with(self::MISSION_ID)
      ->willReturn(new MissionAssignmentContext(
        self::MISSION_ID,
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

    $manager = new MissionResourceManager($resources, new MissionMemberPolicy($members));

    self::assertSame(
      'organization.missions.execute',
      $manager->mutationPermission(self::MISSION_ID, self::USER_ID),
    );
  }

  private function managerWithMissionStatus(string $status): MissionResourceManager
  {
    $resources = $this->createMock(MissionResourceGatewayPort::class);
    $resources->expects(self::once())->method('missionMutationContext')
      ->with(self::MISSION_ID)
      ->willReturn(new MissionAssignmentContext(self::MISSION_ID, self::ORGANIZATION_ID, $status));

    return new MissionResourceManager($resources);
  }
}
