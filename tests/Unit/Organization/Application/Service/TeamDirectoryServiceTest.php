<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\Service;

use DateTimeImmutable;
use Organization\Application\Contract\Team\TeamMembershipSnapshot;
use Organization\Application\Port\Outbound\TeamRepositoryPort;
use Organization\Application\Service\TeamDirectoryService;
use Organization\Domain\Model\Team\Team;
use Organization\Domain\ValueObject\{OrganizationId, TeamId, TeamName};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(TeamDirectoryService::class)]
final class TeamDirectoryServiceTest extends TestCase
{
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440400';

  private const string FOREIGN_ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440777';

  private const string TEAM_ID = '550e8400-e29b-41d4-a716-446655440401';

  #[Test]
  public function testResolveTeamReturnsSnapshotWithActiveMembersOnly(): void
  {
    $team = $this->team();

    /** @var TeamRepositoryPort&MockObject $teamRepository */
    $teamRepository = $this->createMock(TeamRepositoryPort::class);
    $teamRepository->expects(self::once())->method('findById')->willReturn($team);
    $teamRepository->expects(self::once())
      ->method('findActiveMemberIds')
      ->willReturn(['member-1', 'member-2']);

    $service = new TeamDirectoryService($teamRepository);

    $snapshot = $service->resolveTeam(self::ORGANIZATION_ID, self::TEAM_ID);

    self::assertInstanceOf(TeamMembershipSnapshot::class, $snapshot);
    self::assertSame(self::TEAM_ID, $snapshot->teamId);
    self::assertSame('Field crew A', $snapshot->name);
    self::assertSame(['member-1', 'member-2'], $snapshot->memberIds);
  }

  #[Test]
  public function testResolveTeamReturnsNullForForeignOrganization(): void
  {
    $team = $this->team();

    /** @var TeamRepositoryPort&MockObject $teamRepository */
    $teamRepository = $this->createMock(TeamRepositoryPort::class);
    $teamRepository->method('findById')->willReturn($team);
    $teamRepository->expects(self::never())->method('findActiveMemberIds');

    $service = new TeamDirectoryService($teamRepository);

    self::assertNull($service->resolveTeam(self::FOREIGN_ORGANIZATION_ID, self::TEAM_ID));
  }

  #[Test]
  public function testResolveTeamReturnsNullWhenTeamMissing(): void
  {
    $teamRepository = $this->createStub(TeamRepositoryPort::class);
    $teamRepository->method('findById')->willReturn(null);

    $service = new TeamDirectoryService($teamRepository);

    self::assertNull($service->resolveTeam(self::ORGANIZATION_ID, self::TEAM_ID));
  }

  #[Test]
  public function testResolveTeamReturnsNullForMalformedTeamId(): void
  {
    $service = new TeamDirectoryService($this->createStub(TeamRepositoryPort::class));

    self::assertNull($service->resolveTeam(self::ORGANIZATION_ID, 'not-a-uuid'));
  }

  #[Test]
  public function testListActiveMemberIdsReturnsEmptyForMissingTeam(): void
  {
    $teamRepository = $this->createStub(TeamRepositoryPort::class);
    $teamRepository->method('findById')->willReturn(null);

    $service = new TeamDirectoryService($teamRepository);

    self::assertSame([], $service->listActiveMemberIds(self::ORGANIZATION_ID, self::TEAM_ID));
  }

  #[Test]
  public function testListActiveMemberIdsReturnsActiveMembers(): void
  {
    $team = $this->team();

    $teamRepository = $this->createStub(TeamRepositoryPort::class);
    $teamRepository->method('findById')->willReturn($team);
    $teamRepository->method('findActiveMemberIds')->willReturn(['member-1']);

    $service = new TeamDirectoryService($teamRepository);

    self::assertSame(['member-1'], $service->listActiveMemberIds(self::ORGANIZATION_ID, self::TEAM_ID));
  }

  private function team(): Team
  {
    return Team::reconstitute(
      id: TeamId::fromString(self::TEAM_ID),
      organizationId: OrganizationId::fromString(self::ORGANIZATION_ID),
      name: new TeamName('Field crew A'),
      description: '',
      createdAt: new DateTimeImmutable('-1 day'),
      updatedAt: new DateTimeImmutable('-1 day'),
    );
  }
}
