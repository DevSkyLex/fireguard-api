<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\UseCase\Command\Team\RemoveTeamMember;

use DateTimeImmutable;
use Organization\Application\Port\Outbound\{OrganizationRepositoryPort, TeamRepositoryPort};
use Organization\Application\UseCase\Command\Team\RemoveTeamMember\{RemoveTeamMemberCommand, RemoveTeamMemberHandler, RemoveTeamMemberResult};
use Organization\Domain\Event\Team\TeamMemberRemovedEvent;
use Organization\Domain\Exception\{OrganizationNotFoundException, TeamMemberNotFoundException, TeamNotFoundException};
use Organization\Domain\Model\Organization\Organization;
use Organization\Domain\Model\Team\Team;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationMemberId, OrganizationName, TeamId, TeamName};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\EventDispatcherPort;

#[CoversClass(RemoveTeamMemberHandler::class)]
final class RemoveTeamMemberHandlerTest extends TestCase
{
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440500';

  private const string FOREIGN_ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440888';

  private const string TEAM_ID = '550e8400-e29b-41d4-a716-446655440501';

  private const string MEMBER_ID = '550e8400-e29b-41d4-a716-446655440502';

  #[Test]
  public function testInvokeRemovesMemberFromTeam(): void
  {
    $organizationRepository = $this->organizationRepository();
    $team = $this->team(self::ORGANIZATION_ID);

    /** @var TeamRepositoryPort&MockObject $teamRepository */
    $teamRepository = $this->createMock(TeamRepositoryPort::class);
    $teamRepository->expects(self::once())->method('findById')->willReturn($team);
    $teamRepository->expects(self::once())->method('findMemberIds')->willReturn([self::MEMBER_ID]);
    $teamRepository->expects(self::once())
      ->method('removeMember')
      ->with(self::isInstanceOf(TeamId::class), self::isInstanceOf(OrganizationMemberId::class));

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(
        static fn (object $event): bool => $event instanceof TeamMemberRemovedEvent
          && self::ORGANIZATION_ID === $event->organizationId
          && self::TEAM_ID === $event->teamId
          && self::MEMBER_ID === $event->memberId,
      ));

    $handler = new RemoveTeamMemberHandler(
      organizationRepository: $organizationRepository,
      teamRepository: $teamRepository,
      eventDispatcher: $eventDispatcher,
    );

    $result = $handler->__invoke(new RemoveTeamMemberCommand(
      organizationId: self::ORGANIZATION_ID,
      teamId: self::TEAM_ID,
      memberId: self::MEMBER_ID,
    ));

    self::assertInstanceOf(RemoveTeamMemberResult::class, $result);
    self::assertSame(self::TEAM_ID, $result->teamId);
    self::assertSame(self::MEMBER_ID, $result->memberId);
  }

  #[Test]
  public function testInvokeThrowsWhenOrganizationDoesNotExist(): void
  {
    $organizationRepository = $this->createStub(OrganizationRepositoryPort::class);
    $organizationRepository->method('findById')->willReturn(null);

    $handler = new RemoveTeamMemberHandler(
      organizationRepository: $organizationRepository,
      teamRepository: $this->createStub(TeamRepositoryPort::class),
      eventDispatcher: $this->createStub(EventDispatcherPort::class),
    );

    $this->expectException(OrganizationNotFoundException::class);

    $handler->__invoke(new RemoveTeamMemberCommand(
      organizationId: self::ORGANIZATION_ID,
      teamId: self::TEAM_ID,
      memberId: self::MEMBER_ID,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenTeamNotFound(): void
  {
    $organizationRepository = $this->organizationRepository();

    $teamRepository = $this->createStub(TeamRepositoryPort::class);
    $teamRepository->method('findById')->willReturn(null);

    $handler = new RemoveTeamMemberHandler(
      organizationRepository: $organizationRepository,
      teamRepository: $teamRepository,
      eventDispatcher: $this->createStub(EventDispatcherPort::class),
    );

    $this->expectException(TeamNotFoundException::class);

    $handler->__invoke(new RemoveTeamMemberCommand(
      organizationId: self::ORGANIZATION_ID,
      teamId: self::TEAM_ID,
      memberId: self::MEMBER_ID,
    ));
  }

  #[Test]
  public function testInvokeRejectsTeamFromForeignOrganization(): void
  {
    $organizationRepository = $this->organizationRepository();
    $foreignTeam = $this->team(self::FOREIGN_ORGANIZATION_ID);

    $teamRepository = $this->createStub(TeamRepositoryPort::class);
    $teamRepository->method('findById')->willReturn($foreignTeam);

    $handler = new RemoveTeamMemberHandler(
      organizationRepository: $organizationRepository,
      teamRepository: $teamRepository,
      eventDispatcher: $this->createStub(EventDispatcherPort::class),
    );

    $this->expectException(TeamNotFoundException::class);

    $handler->__invoke(new RemoveTeamMemberCommand(
      organizationId: self::ORGANIZATION_ID,
      teamId: self::TEAM_ID,
      memberId: self::MEMBER_ID,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenMemberNotPartOfTeam(): void
  {
    $organizationRepository = $this->organizationRepository();
    $team = $this->team(self::ORGANIZATION_ID);

    /** @var TeamRepositoryPort&MockObject $teamRepository */
    $teamRepository = $this->createMock(TeamRepositoryPort::class);
    $teamRepository->method('findById')->willReturn($team);
    $teamRepository->method('findMemberIds')->willReturn([]);
    $teamRepository->expects(self::never())->method('removeMember');

    $handler = new RemoveTeamMemberHandler(
      organizationRepository: $organizationRepository,
      teamRepository: $teamRepository,
      eventDispatcher: $this->createStub(EventDispatcherPort::class),
    );

    $this->expectException(TeamMemberNotFoundException::class);

    $handler->__invoke(new RemoveTeamMemberCommand(
      organizationId: self::ORGANIZATION_ID,
      teamId: self::TEAM_ID,
      memberId: self::MEMBER_ID,
    ));
  }

  private function organizationRepository(): OrganizationRepositoryPort
  {
    $organization = Organization::reconstitute(
      id: new OrganizationId(self::ORGANIZATION_ID),
      name: new OrganizationName('Fireguard Nice'),
      createdByUserId: '550e8400-e29b-41d4-a716-446655440001',
      isActive: true,
      createdAt: new DateTimeImmutable('-2 days'),
    );

    $repository = $this->createStub(OrganizationRepositoryPort::class);
    $repository->method('findById')->willReturn($organization);

    return $repository;
  }

  private function team(string $organizationId): Team
  {
    return Team::reconstitute(
      id: TeamId::fromString(self::TEAM_ID),
      organizationId: OrganizationId::fromString($organizationId),
      name: new TeamName('Field crew A'),
      description: '',
      createdAt: new DateTimeImmutable('-1 day'),
      updatedAt: new DateTimeImmutable('-1 day'),
    );
  }
}
