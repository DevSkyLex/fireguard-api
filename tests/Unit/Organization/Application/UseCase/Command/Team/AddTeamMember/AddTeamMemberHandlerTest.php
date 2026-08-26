<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\UseCase\Command\Team\AddTeamMember;

use DateTimeImmutable;
use Organization\Application\Port\Outbound\{OrganizationMemberRepositoryPort, OrganizationRepositoryPort, TeamRepositoryPort};
use Organization\Application\UseCase\Command\Team\AddTeamMember\{AddTeamMemberCommand, AddTeamMemberHandler, AddTeamMemberResult};
use Organization\Domain\Event\Team\TeamMemberAddedEvent;
use Organization\Domain\Exception\{OrganizationMemberNotFoundException, OrganizationNotFoundException, TeamNotFoundException};
use Organization\Domain\Exception\TeamMembershipException;
use Organization\Domain\Model\Organization\Organization;
use Organization\Domain\Model\OrganizationMember\OrganizationMember;
use Organization\Domain\Model\Team\Team;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationMemberId, OrganizationName, TeamId, TeamName};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\EventDispatcherPort;

#[CoversClass(AddTeamMemberHandler::class)]
final class AddTeamMemberHandlerTest extends TestCase
{
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440400';

  private const string FOREIGN_ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440777';

  private const string TEAM_ID = '550e8400-e29b-41d4-a716-446655440401';

  private const string MEMBER_ID = '550e8400-e29b-41d4-a716-446655440402';

  #[Test]
  public function testInvokeAddsActiveMemberToTeam(): void
  {
    $organizationRepository = $this->organizationRepository();
    $team = $this->team();
    $member = $this->activeMember(self::ORGANIZATION_ID);

    /** @var TeamRepositoryPort&MockObject $teamRepository */
    $teamRepository = $this->createMock(TeamRepositoryPort::class);
    $teamRepository->expects(self::once())->method('findById')->willReturn($team);
    $teamRepository->expects(self::once())->method('findMemberIds')->willReturn([]);
    $teamRepository->expects(self::once())
      ->method('addMember')
      ->with(self::isInstanceOf(TeamId::class), self::isInstanceOf(OrganizationMemberId::class), 'lead');

    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::once())->method('findById')->willReturn($member);

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(
        static fn (object $event): bool => $event instanceof TeamMemberAddedEvent
          && self::TEAM_ID === $event->teamId
          && self::MEMBER_ID === $event->memberId
          && 'lead' === $event->role,
      ));

    $handler = new AddTeamMemberHandler(
      organizationRepository: $organizationRepository,
      teamRepository: $teamRepository,
      memberRepository: $memberRepository,
      eventDispatcher: $eventDispatcher,
    );

    $result = $handler->__invoke(new AddTeamMemberCommand(
      organizationId: self::ORGANIZATION_ID,
      teamId: self::TEAM_ID,
      memberId: self::MEMBER_ID,
      role: 'lead',
    ));

    self::assertInstanceOf(AddTeamMemberResult::class, $result);
    self::assertSame(self::TEAM_ID, $result->teamId);
    self::assertSame(self::MEMBER_ID, $result->memberId);
    self::assertSame('lead', $result->role);
  }

  #[Test]
  public function testInvokeThrowsWhenOrganizationDoesNotExist(): void
  {
    $organizationRepository = $this->createStub(OrganizationRepositoryPort::class);
    $organizationRepository->method('findById')->willReturn(null);

    $handler = new AddTeamMemberHandler(
      organizationRepository: $organizationRepository,
      teamRepository: $this->createStub(TeamRepositoryPort::class),
      memberRepository: $this->createStub(OrganizationMemberRepositoryPort::class),
      eventDispatcher: $this->createStub(EventDispatcherPort::class),
    );

    $this->expectException(OrganizationNotFoundException::class);

    $handler->__invoke(new AddTeamMemberCommand(
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

    $handler = new AddTeamMemberHandler(
      organizationRepository: $organizationRepository,
      teamRepository: $teamRepository,
      memberRepository: $this->createStub(OrganizationMemberRepositoryPort::class),
      eventDispatcher: $this->createStub(EventDispatcherPort::class),
    );

    $this->expectException(TeamNotFoundException::class);

    $handler->__invoke(new AddTeamMemberCommand(
      organizationId: self::ORGANIZATION_ID,
      teamId: self::TEAM_ID,
      memberId: self::MEMBER_ID,
    ));
  }

  #[Test]
  public function testInvokeRejectsMemberFromForeignOrganization(): void
  {
    $organizationRepository = $this->organizationRepository();
    $team = $this->team();
    $foreignMember = $this->activeMember(self::FOREIGN_ORGANIZATION_ID);

    $teamRepository = $this->createStub(TeamRepositoryPort::class);
    $teamRepository->method('findById')->willReturn($team);

    $memberRepository = $this->createStub(OrganizationMemberRepositoryPort::class);
    $memberRepository->method('findById')->willReturn($foreignMember);

    $handler = new AddTeamMemberHandler(
      organizationRepository: $organizationRepository,
      teamRepository: $teamRepository,
      memberRepository: $memberRepository,
      eventDispatcher: $this->createStub(EventDispatcherPort::class),
    );

    $this->expectException(OrganizationMemberNotFoundException::class);

    $handler->__invoke(new AddTeamMemberCommand(
      organizationId: self::ORGANIZATION_ID,
      teamId: self::TEAM_ID,
      memberId: self::MEMBER_ID,
    ));
  }

  #[Test]
  public function testInvokeRejectsInactiveMember(): void
  {
    $organizationRepository = $this->organizationRepository();
    $team = $this->team();

    $member = OrganizationMember::reconstitute(
      id: OrganizationMemberId::fromString(self::MEMBER_ID),
      organizationId: OrganizationId::fromString(self::ORGANIZATION_ID),
      userId: '550e8400-e29b-41d4-a716-446655440999',
      isActive: false,
      joinedAt: new DateTimeImmutable('-10 days'),
    );

    $teamRepository = $this->createStub(TeamRepositoryPort::class);
    $teamRepository->method('findById')->willReturn($team);

    $memberRepository = $this->createStub(OrganizationMemberRepositoryPort::class);
    $memberRepository->method('findById')->willReturn($member);

    $handler = new AddTeamMemberHandler(
      organizationRepository: $organizationRepository,
      teamRepository: $teamRepository,
      memberRepository: $memberRepository,
      eventDispatcher: $this->createStub(EventDispatcherPort::class),
    );

    $this->expectException(TeamMembershipException::class);
    $this->expectExceptionMessage('Member is not active in this organization.');

    $handler->__invoke(new AddTeamMemberCommand(
      organizationId: self::ORGANIZATION_ID,
      teamId: self::TEAM_ID,
      memberId: self::MEMBER_ID,
    ));
  }

  #[Test]
  public function testInvokeRejectsDuplicateMembership(): void
  {
    $organizationRepository = $this->organizationRepository();
    $team = $this->team();
    $member = $this->activeMember(self::ORGANIZATION_ID);

    /** @var TeamRepositoryPort&MockObject $teamRepository */
    $teamRepository = $this->createMock(TeamRepositoryPort::class);
    $teamRepository->method('findById')->willReturn($team);
    $teamRepository->method('findMemberIds')->willReturn([self::MEMBER_ID]);
    $teamRepository->expects(self::never())->method('addMember');

    $memberRepository = $this->createStub(OrganizationMemberRepositoryPort::class);
    $memberRepository->method('findById')->willReturn($member);

    $handler = new AddTeamMemberHandler(
      organizationRepository: $organizationRepository,
      teamRepository: $teamRepository,
      memberRepository: $memberRepository,
      eventDispatcher: $this->createStub(EventDispatcherPort::class),
    );

    $this->expectException(TeamMembershipException::class);
    $this->expectExceptionMessage('Member is already part of this team.');

    $handler->__invoke(new AddTeamMemberCommand(
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

  private function activeMember(string $organizationId): OrganizationMember
  {
    return OrganizationMember::reconstitute(
      id: OrganizationMemberId::fromString(self::MEMBER_ID),
      organizationId: OrganizationId::fromString($organizationId),
      userId: '550e8400-e29b-41d4-a716-446655440999',
      isActive: true,
      joinedAt: new DateTimeImmutable('-10 days'),
    );
  }
}
