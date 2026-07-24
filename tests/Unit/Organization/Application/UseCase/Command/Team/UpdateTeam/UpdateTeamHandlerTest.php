<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\UseCase\Command\Team\UpdateTeam;

use DateTimeImmutable;
use Organization\Application\Port\Outbound\{OrganizationRepositoryPort, TeamRepositoryPort};
use Organization\Application\UseCase\Command\Team\UpdateTeam\{UpdateTeamCommand, UpdateTeamHandler, UpdateTeamResult};
use Organization\Domain\Event\Team\TeamUpdatedEvent;
use Organization\Domain\Exception\{OrganizationNotFoundException, TeamNameAlreadyExistsException, TeamNotFoundException};
use Organization\Domain\Model\Organization\Organization;
use Organization\Domain\Model\Team\Team;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationName, TeamId, TeamName};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\EventDispatcherPort;

/**
 * Test UpdateTeamHandlerTest.
 *
 * @category Test
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(UpdateTeamHandler::class)]
final class UpdateTeamHandlerTest extends TestCase
{
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440400';

  private const string OTHER_ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440500';

  private const string TEAM_ID = '550e8400-e29b-41d4-a716-446655440401';

  private const string OTHER_TEAM_ID = '550e8400-e29b-41d4-a716-446655440499';

  #[Test]
  public function testInvokeUpdatesNameAndDescription(): void
  {
    $team = $this->team(name: 'Field crew A', description: 'Handles rooftops');

    $organizationRepository = $this->createStub(OrganizationRepositoryPort::class);
    $organizationRepository->method('findById')->willReturn($this->organization());

    /** @var TeamRepositoryPort&MockObject $teamRepository */
    $teamRepository = $this->createMock(TeamRepositoryPort::class);
    $teamRepository->method('findById')->willReturn($team);
    $teamRepository->method('findByOrganizationAndName')->willReturn(null);
    $teamRepository->expects(self::once())->method('save')->with($team);

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(
        static fn (object $event): bool => $event instanceof TeamUpdatedEvent
          && self::ORGANIZATION_ID === $event->organizationId
          && self::TEAM_ID === $event->teamId
          && 'Field crew B' === $event->name,
      ));

    $handler = new UpdateTeamHandler($organizationRepository, $teamRepository, $eventDispatcher);

    $result = $handler->__invoke(new UpdateTeamCommand(
      organizationId: self::ORGANIZATION_ID,
      teamId: self::TEAM_ID,
      name: 'Field crew B',
      description: 'Handles gutters',
    ));

    self::assertInstanceOf(UpdateTeamResult::class, $result);
    self::assertSame(self::TEAM_ID, $result->id);
    self::assertSame(self::ORGANIZATION_ID, $result->organizationId);
    self::assertSame('Field crew B', $result->name);
    self::assertSame('Handles gutters', $result->description);
    self::assertSame('Field crew B', (string) $team->name());
    self::assertSame('Handles gutters', $team->description());
  }

  #[Test]
  public function testInvokeUpdatesDescriptionOnlyWhenNameOmitted(): void
  {
    $team = $this->team(name: 'Field crew A', description: 'Handles rooftops');

    $organizationRepository = $this->createStub(OrganizationRepositoryPort::class);
    $organizationRepository->method('findById')->willReturn($this->organization());

    /** @var TeamRepositoryPort&MockObject $teamRepository */
    $teamRepository = $this->createMock(TeamRepositoryPort::class);
    $teamRepository->method('findById')->willReturn($team);
    $teamRepository->expects(self::never())->method('findByOrganizationAndName');
    $teamRepository->expects(self::once())->method('save')->with($team);

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(
        static fn (object $event): bool => $event instanceof TeamUpdatedEvent
          && 'Field crew A' === $event->name,
      ));

    $handler = new UpdateTeamHandler($organizationRepository, $teamRepository, $eventDispatcher);

    $result = $handler->__invoke(new UpdateTeamCommand(
      organizationId: self::ORGANIZATION_ID,
      teamId: self::TEAM_ID,
      description: 'Handles gutters',
    ));

    self::assertSame('Field crew A', $result->name);
    self::assertSame('Handles gutters', $result->description);
  }

  #[Test]
  public function testInvokeSkipsDuplicateCheckWhenNameUnchanged(): void
  {
    $team = $this->team(name: 'Field crew A', description: 'Handles rooftops');

    $organizationRepository = $this->createStub(OrganizationRepositoryPort::class);
    $organizationRepository->method('findById')->willReturn($this->organization());

    /** @var TeamRepositoryPort&MockObject $teamRepository */
    $teamRepository = $this->createMock(TeamRepositoryPort::class);
    $teamRepository->method('findById')->willReturn($team);
    $teamRepository->expects(self::never())->method('findByOrganizationAndName');
    $teamRepository->expects(self::once())->method('save')->with($team);

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())->method('dispatch');

    $handler = new UpdateTeamHandler($organizationRepository, $teamRepository, $eventDispatcher);

    $result = $handler->__invoke(new UpdateTeamCommand(
      organizationId: self::ORGANIZATION_ID,
      teamId: self::TEAM_ID,
      name: 'Field crew A',
    ));

    self::assertSame('Field crew A', $result->name);
  }

  #[Test]
  public function testInvokeAllowsRenameWhenMatchIsTheSameTeam(): void
  {
    $team = $this->team(name: 'Field crew A', description: 'Handles rooftops');

    $organizationRepository = $this->createStub(OrganizationRepositoryPort::class);
    $organizationRepository->method('findById')->willReturn($this->organization());

    /** @var TeamRepositoryPort&MockObject $teamRepository */
    $teamRepository = $this->createMock(TeamRepositoryPort::class);
    $teamRepository->method('findById')->willReturn($team);
    $teamRepository->method('findByOrganizationAndName')->willReturn($team);
    $teamRepository->expects(self::once())->method('save')->with($team);

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())->method('dispatch');

    $handler = new UpdateTeamHandler($organizationRepository, $teamRepository, $eventDispatcher);

    $result = $handler->__invoke(new UpdateTeamCommand(
      organizationId: self::ORGANIZATION_ID,
      teamId: self::TEAM_ID,
      name: 'Field crew B',
    ));

    self::assertSame('Field crew B', $result->name);
  }

  #[Test]
  public function testInvokeIsNoOpWhenNoFieldsProvided(): void
  {
    $team = $this->team(name: 'Field crew A', description: 'Handles rooftops');

    $organizationRepository = $this->createStub(OrganizationRepositoryPort::class);
    $organizationRepository->method('findById')->willReturn($this->organization());

    /** @var TeamRepositoryPort&MockObject $teamRepository */
    $teamRepository = $this->createMock(TeamRepositoryPort::class);
    $teamRepository->method('findById')->willReturn($team);
    $teamRepository->expects(self::never())->method('findByOrganizationAndName');
    $teamRepository->expects(self::once())->method('save')->with($team);

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())->method('dispatch');

    $handler = new UpdateTeamHandler($organizationRepository, $teamRepository, $eventDispatcher);

    $result = $handler->__invoke(new UpdateTeamCommand(
      organizationId: self::ORGANIZATION_ID,
      teamId: self::TEAM_ID,
    ));

    self::assertSame('Field crew A', $result->name);
    self::assertSame('Handles rooftops', $result->description);
  }

  #[Test]
  public function testInvokeThrowsWhenOrganizationDoesNotExist(): void
  {
    $organizationRepository = $this->createStub(OrganizationRepositoryPort::class);
    $organizationRepository->method('findById')->willReturn(null);

    /** @var TeamRepositoryPort&MockObject $teamRepository */
    $teamRepository = $this->createMock(TeamRepositoryPort::class);
    $teamRepository->expects(self::never())->method('save');

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new UpdateTeamHandler($organizationRepository, $teamRepository, $eventDispatcher);

    $this->expectException(OrganizationNotFoundException::class);

    $handler->__invoke(new UpdateTeamCommand(
      organizationId: self::ORGANIZATION_ID,
      teamId: self::TEAM_ID,
      name: 'Field crew B',
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenTeamDoesNotExist(): void
  {
    $organizationRepository = $this->createStub(OrganizationRepositoryPort::class);
    $organizationRepository->method('findById')->willReturn($this->organization());

    /** @var TeamRepositoryPort&MockObject $teamRepository */
    $teamRepository = $this->createMock(TeamRepositoryPort::class);
    $teamRepository->method('findById')->willReturn(null);
    $teamRepository->expects(self::never())->method('save');

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new UpdateTeamHandler($organizationRepository, $teamRepository, $eventDispatcher);

    $this->expectException(TeamNotFoundException::class);

    $handler->__invoke(new UpdateTeamCommand(
      organizationId: self::ORGANIZATION_ID,
      teamId: self::TEAM_ID,
      name: 'Field crew B',
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenTeamBelongsToAnotherOrganization(): void
  {
    $team = $this->team(organizationId: self::OTHER_ORGANIZATION_ID, name: 'Field crew A');

    $organizationRepository = $this->createStub(OrganizationRepositoryPort::class);
    $organizationRepository->method('findById')->willReturn($this->organization());

    /** @var TeamRepositoryPort&MockObject $teamRepository */
    $teamRepository = $this->createMock(TeamRepositoryPort::class);
    $teamRepository->method('findById')->willReturn($team);
    $teamRepository->expects(self::never())->method('save');

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new UpdateTeamHandler($organizationRepository, $teamRepository, $eventDispatcher);

    $this->expectException(TeamNotFoundException::class);

    $handler->__invoke(new UpdateTeamCommand(
      organizationId: self::ORGANIZATION_ID,
      teamId: self::TEAM_ID,
      name: 'Field crew B',
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenNewNameAlreadyExists(): void
  {
    $team = $this->team(name: 'Field crew A');
    $conflicting = $this->team(teamId: self::OTHER_TEAM_ID, name: 'Field crew B');

    $organizationRepository = $this->createStub(OrganizationRepositoryPort::class);
    $organizationRepository->method('findById')->willReturn($this->organization());

    /** @var TeamRepositoryPort&MockObject $teamRepository */
    $teamRepository = $this->createMock(TeamRepositoryPort::class);
    $teamRepository->method('findById')->willReturn($team);
    $teamRepository->method('findByOrganizationAndName')->willReturn($conflicting);
    $teamRepository->expects(self::never())->method('save');

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new UpdateTeamHandler($organizationRepository, $teamRepository, $eventDispatcher);

    $this->expectException(TeamNameAlreadyExistsException::class);

    $handler->__invoke(new UpdateTeamCommand(
      organizationId: self::ORGANIZATION_ID,
      teamId: self::TEAM_ID,
      name: 'Field crew B',
    ));
  }

  private function organization(): Organization
  {
    return Organization::reconstitute(
      id: new OrganizationId(self::ORGANIZATION_ID),
      name: new OrganizationName('Fireguard Nice'),
      createdByUserId: '550e8400-e29b-41d4-a716-446655440001',
      isActive: true,
      createdAt: new DateTimeImmutable('-2 days'),
    );
  }

  private function team(
    string $teamId = self::TEAM_ID,
    string $organizationId = self::ORGANIZATION_ID,
    string $name = 'Field crew A',
    string $description = 'Handles rooftops',
  ): Team {
    return Team::reconstitute(
      id: TeamId::fromString($teamId),
      organizationId: OrganizationId::fromString($organizationId),
      name: new TeamName($name),
      description: $description,
      createdAt: new DateTimeImmutable('-2 days'),
      updatedAt: new DateTimeImmutable('-2 days'),
    );
  }
}
