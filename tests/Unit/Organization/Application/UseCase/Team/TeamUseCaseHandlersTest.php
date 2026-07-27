<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\UseCase\Team;

use DateTimeImmutable;
use Organization\Application\Port\Outbound\{OrganizationRepositoryPort, TeamRepositoryPort};
use Organization\Application\UseCase\Command\Team\DeleteTeam\{DeleteTeamCommand, DeleteTeamHandler};
use Organization\Application\UseCase\Command\Team\RemoveTeamMember\{
  RemoveTeamMemberCommand,
  RemoveTeamMemberHandler
};
use Organization\Application\UseCase\Command\Team\UpdateTeam\{UpdateTeamCommand, UpdateTeamHandler};
use Organization\Application\UseCase\Query\Team\GetTeam\{GetTeamHandler, GetTeamQuery};
use Organization\Application\UseCase\Query\Team\ListTeamMembers\{
  ListTeamMembersHandler,
  ListTeamMembersQuery
};
use Organization\Domain\Event\Team\{TeamDeletedEvent, TeamMemberRemovedEvent, TeamUpdatedEvent};
use Organization\Domain\Exception\{
  OrganizationNotFoundException,
  TeamMemberNotFoundException,
  TeamNameAlreadyExistsException,
  TeamNotFoundException
};
use Organization\Domain\Model\Organization\Organization;
use Organization\Domain\Model\Team\Team;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationName, TeamId, TeamName};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\EventDispatcherPort;

/**
 * Test TeamUseCaseHandlersTest.
 *
 * Covers the team command and query handlers, which share the same
 * organization -> team resolution guard before doing their own work.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(UpdateTeamHandler::class)]
#[CoversClass(DeleteTeamHandler::class)]
#[CoversClass(RemoveTeamMemberHandler::class)]
#[CoversClass(GetTeamHandler::class)]
#[CoversClass(ListTeamMembersHandler::class)]
final class TeamUseCaseHandlersTest extends TestCase
{
  // #region Constants
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440010';

  private const string OTHER_ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440019';

  private const string TEAM_ID = '550e8400-e29b-41d4-a716-446655440030';

  private const string MEMBER_ID = '550e8400-e29b-41d4-a716-446655440011';
  // #endregion

  // #region Methods
  #[Test]
  public function testUpdateTeamRenamesDescribesSavesAndDispatches(): void
  {
    $team = $this->team();

    /** @var TeamRepositoryPort&MockObject $teamRepository */
    $teamRepository = $this->createMock(TeamRepositoryPort::class);
    $teamRepository->method('findById')->willReturn($team);
    $teamRepository->method('findByOrganizationAndName')->willReturn(null);
    $teamRepository->expects(self::once())->method('save')->with($team);

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (TeamUpdatedEvent $event): bool => self::TEAM_ID === $event->teamId
        && 'Night shift' === $event->name));

    $handler = new UpdateTeamHandler(
      organizationRepository: $this->organizationRepository(),
      teamRepository: $teamRepository,
      eventDispatcher: $eventDispatcher,
    );

    $result = $handler(new UpdateTeamCommand(
      organizationId: self::ORGANIZATION_ID,
      teamId: self::TEAM_ID,
      name: 'Night shift',
      description: 'Covers 22:00-06:00',
    ));

    self::assertSame('Night shift', $result->name);
    self::assertSame('Covers 22:00-06:00', $result->description);
  }

  #[Test]
  public function testUpdateTeamKeepsTheNameWhenTheCommandOmitsIt(): void
  {
    $team = $this->team();

    /** @var TeamRepositoryPort&MockObject $teamRepository */
    $teamRepository = $this->createMock(TeamRepositoryPort::class);
    $teamRepository->method('findById')->willReturn($team);
    $teamRepository->expects(self::never())->method('findByOrganizationAndName');
    $teamRepository->expects(self::once())->method('save');

    $handler = new UpdateTeamHandler(
      organizationRepository: $this->organizationRepository(),
      teamRepository: $teamRepository,
      eventDispatcher: $this->createStub(EventDispatcherPort::class),
    );

    $result = $handler(new UpdateTeamCommand(
      organizationId: self::ORGANIZATION_ID,
      teamId: self::TEAM_ID,
      name: null,
      description: null,
    ));

    self::assertSame('Day shift', $result->name);
  }

  #[Test]
  public function testUpdateTeamRejectsANameAlreadyTakenByAnotherTeam(): void
  {
    $teamRepository = $this->createStub(TeamRepositoryPort::class);
    $teamRepository->method('findById')->willReturn($this->team());
    $teamRepository->method('findByOrganizationAndName')->willReturn(Team::create(
      id: TeamId::fromString('550e8400-e29b-41d4-a716-446655440031'),
      organizationId: OrganizationId::fromString(self::ORGANIZATION_ID),
      name: new TeamName('Night shift'),
    ));

    $handler = new UpdateTeamHandler(
      organizationRepository: $this->organizationRepository(),
      teamRepository: $teamRepository,
      eventDispatcher: $this->createStub(EventDispatcherPort::class),
    );

    $this->expectException(TeamNameAlreadyExistsException::class);

    $handler(new UpdateTeamCommand(
      organizationId: self::ORGANIZATION_ID,
      teamId: self::TEAM_ID,
      name: 'Night shift',
    ));
  }

  #[Test]
  public function testUpdateTeamThrowsWhenTheOrganizationIsUnknown(): void
  {
    $handler = new UpdateTeamHandler(
      organizationRepository: $this->organizationRepository(found: false),
      teamRepository: $this->createStub(TeamRepositoryPort::class),
      eventDispatcher: $this->createStub(EventDispatcherPort::class),
    );

    $this->expectException(OrganizationNotFoundException::class);

    $handler(new UpdateTeamCommand(organizationId: self::ORGANIZATION_ID, teamId: self::TEAM_ID));
  }

  #[Test]
  public function testUpdateTeamThrowsWhenTheTeamBelongsToAnotherOrganization(): void
  {
    $teamRepository = $this->createStub(TeamRepositoryPort::class);
    $teamRepository->method('findById')->willReturn(Team::create(
      id: TeamId::fromString(self::TEAM_ID),
      organizationId: OrganizationId::fromString(self::OTHER_ORGANIZATION_ID),
      name: new TeamName('Day shift'),
    ));

    $handler = new UpdateTeamHandler(
      organizationRepository: $this->organizationRepository(),
      teamRepository: $teamRepository,
      eventDispatcher: $this->createStub(EventDispatcherPort::class),
    );

    $this->expectException(TeamNotFoundException::class);

    $handler(new UpdateTeamCommand(organizationId: self::ORGANIZATION_ID, teamId: self::TEAM_ID));
  }

  #[Test]
  public function testDeleteTeamRemovesTheTeamAndDispatches(): void
  {
    $team = $this->team();

    /** @var TeamRepositoryPort&MockObject $teamRepository */
    $teamRepository = $this->createMock(TeamRepositoryPort::class);
    $teamRepository->method('findById')->willReturn($team);
    $teamRepository->expects(self::once())->method('remove')->with($team);

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())
      ->method('dispatch')
      ->with(self::isInstanceOf(TeamDeletedEvent::class));

    $handler = new DeleteTeamHandler(
      organizationRepository: $this->organizationRepository(),
      teamRepository: $teamRepository,
      eventDispatcher: $eventDispatcher,
    );

    $result = $handler(new DeleteTeamCommand(
      organizationId: self::ORGANIZATION_ID,
      teamId: self::TEAM_ID,
    ));

    self::assertSame(self::TEAM_ID, $result->teamId);
    self::assertSame(self::ORGANIZATION_ID, $result->organizationId);
  }

  #[Test]
  public function testDeleteTeamThrowsWhenTheTeamIsUnknown(): void
  {
    $teamRepository = $this->createStub(TeamRepositoryPort::class);
    $teamRepository->method('findById')->willReturn(null);

    $handler = new DeleteTeamHandler(
      organizationRepository: $this->organizationRepository(),
      teamRepository: $teamRepository,
      eventDispatcher: $this->createStub(EventDispatcherPort::class),
    );

    $this->expectException(TeamNotFoundException::class);

    $handler(new DeleteTeamCommand(organizationId: self::ORGANIZATION_ID, teamId: self::TEAM_ID));
  }

  #[Test]
  public function testDeleteTeamThrowsWhenTheOrganizationIsUnknown(): void
  {
    $handler = new DeleteTeamHandler(
      organizationRepository: $this->organizationRepository(found: false),
      teamRepository: $this->createStub(TeamRepositoryPort::class),
      eventDispatcher: $this->createStub(EventDispatcherPort::class),
    );

    $this->expectException(OrganizationNotFoundException::class);

    $handler(new DeleteTeamCommand(organizationId: self::ORGANIZATION_ID, teamId: self::TEAM_ID));
  }

  #[Test]
  public function testRemoveTeamMemberDetachesTheMemberAndDispatches(): void
  {
    /** @var TeamRepositoryPort&MockObject $teamRepository */
    $teamRepository = $this->createMock(TeamRepositoryPort::class);
    $teamRepository->method('findById')->willReturn($this->team());
    $teamRepository->method('findMemberIds')->willReturn([self::MEMBER_ID]);
    $teamRepository->expects(self::once())->method('removeMember');

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())
      ->method('dispatch')
      ->with(self::isInstanceOf(TeamMemberRemovedEvent::class));

    $handler = new RemoveTeamMemberHandler(
      organizationRepository: $this->organizationRepository(),
      teamRepository: $teamRepository,
      eventDispatcher: $eventDispatcher,
    );

    $result = $handler(new RemoveTeamMemberCommand(
      organizationId: self::ORGANIZATION_ID,
      teamId: self::TEAM_ID,
      memberId: self::MEMBER_ID,
    ));

    self::assertSame(self::MEMBER_ID, $result->memberId);
  }

  #[Test]
  public function testRemoveTeamMemberThrowsWhenTheMemberIsNotInTheTeam(): void
  {
    $teamRepository = $this->createStub(TeamRepositoryPort::class);
    $teamRepository->method('findById')->willReturn($this->team());
    $teamRepository->method('findMemberIds')->willReturn([]);

    $handler = new RemoveTeamMemberHandler(
      organizationRepository: $this->organizationRepository(),
      teamRepository: $teamRepository,
      eventDispatcher: $this->createStub(EventDispatcherPort::class),
    );

    $this->expectException(TeamMemberNotFoundException::class);

    $handler(new RemoveTeamMemberCommand(
      organizationId: self::ORGANIZATION_ID,
      teamId: self::TEAM_ID,
      memberId: self::MEMBER_ID,
    ));
  }

  #[Test]
  public function testGetTeamReturnsTheTeamWithItsMemberCount(): void
  {
    $teamRepository = $this->createStub(TeamRepositoryPort::class);
    $teamRepository->method('findById')->willReturn($this->team());
    $teamRepository->method('countMembers')->willReturn(7);

    $handler = new GetTeamHandler(
      organizationRepository: $this->organizationRepository(),
      teamRepository: $teamRepository,
    );

    $result = $handler(new GetTeamQuery(self::ORGANIZATION_ID, self::TEAM_ID));

    self::assertSame(self::TEAM_ID, $result->id);
    self::assertSame('Day shift', $result->name);
    self::assertSame(7, $result->memberCount);
  }

  #[Test]
  public function testGetTeamThrowsWhenTheTeamIsUnknown(): void
  {
    $teamRepository = $this->createStub(TeamRepositoryPort::class);
    $teamRepository->method('findById')->willReturn(null);

    $handler = new GetTeamHandler(
      organizationRepository: $this->organizationRepository(),
      teamRepository: $teamRepository,
    );

    $this->expectException(TeamNotFoundException::class);

    $handler(new GetTeamQuery(self::ORGANIZATION_ID, self::TEAM_ID));
  }

  #[Test]
  public function testListTeamMembersMapsEveryMembershipRow(): void
  {
    $teamRepository = $this->createStub(TeamRepositoryPort::class);
    $teamRepository->method('findById')->willReturn($this->team());
    $teamRepository->method('findMemberships')->willReturn([
      ['memberId' => self::MEMBER_ID, 'role' => 'lead', 'addedAt' => new DateTimeImmutable('2026-01-01T00:00:00+00:00')],
      ['memberId' => '550e8400-e29b-41d4-a716-446655440012', 'role' => null, 'addedAt' => new DateTimeImmutable('2026-01-02T00:00:00+00:00')],
    ]);

    $handler = new ListTeamMembersHandler(
      organizationRepository: $this->organizationRepository(),
      teamRepository: $teamRepository,
    );

    $result = $handler(new ListTeamMembersQuery(self::ORGANIZATION_ID, self::TEAM_ID));

    self::assertCount(2, $result->memberships);
    self::assertSame(self::MEMBER_ID, $result->memberships[0]->memberId);
    self::assertSame('lead', $result->memberships[0]->role);
    self::assertNull($result->memberships[1]->role);
  }

  #[Test]
  public function testListTeamMembersThrowsWhenTheOrganizationIsUnknown(): void
  {
    $handler = new ListTeamMembersHandler(
      organizationRepository: $this->organizationRepository(found: false),
      teamRepository: $this->createStub(TeamRepositoryPort::class),
    );

    $this->expectException(OrganizationNotFoundException::class);

    $handler(new ListTeamMembersQuery(self::ORGANIZATION_ID, self::TEAM_ID));
  }

  private function organizationRepository(bool $found = true): OrganizationRepositoryPort
  {
    $repository = $this->createStub(OrganizationRepositoryPort::class);
    $repository->method('findById')->willReturn($found ? $this->organization() : null);

    return $repository;
  }

  private function organization(): Organization
  {
    return Organization::create(
      id: OrganizationId::fromString(self::ORGANIZATION_ID),
      name: new OrganizationName('Fireguard Test'),
      ownerUserId: '550e8400-e29b-41d4-a716-446655440001',
    );
  }

  private function team(): Team
  {
    return Team::create(
      id: TeamId::fromString(self::TEAM_ID),
      organizationId: OrganizationId::fromString(self::ORGANIZATION_ID),
      name: new TeamName('Day shift'),
      description: 'Covers 06:00-22:00',
    );
  }
  // #endregion
}
