<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\UseCase\Command\Team\CreateTeam;

use DateTimeImmutable;
use Organization\Application\Port\Outbound\{OrganizationRepositoryPort, TeamRepositoryPort};
use Organization\Application\UseCase\Command\Team\CreateTeam\{CreateTeamCommand, CreateTeamHandler, CreateTeamResult};
use Organization\Domain\Event\Team\TeamCreatedEvent;
use Organization\Domain\Exception\{OrganizationNotFoundException, TeamNameAlreadyExistsException};
use Organization\Domain\Model\Organization\Organization;
use Organization\Domain\Model\Team\Team;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationName, TeamId, TeamName};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Port\Outbound\EventDispatcherPort;

#[CoversClass(CreateTeamHandler::class)]
final class CreateTeamHandlerTest extends TestCase
{
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440400';

  private const string TEAM_ID = '550e8400-e29b-41d4-a716-446655440401';

  #[Test]
  public function testInvokeCreatesTeam(): void
  {
    $organization = $this->organization();

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())->method('findById')->willReturn($organization);

    $savedTeam = null;
    /** @var TeamRepositoryPort&MockObject $teamRepository */
    $teamRepository = $this->createMock(TeamRepositoryPort::class);
    $teamRepository->expects(self::once())->method('findByOrganizationAndName')->willReturn(null);
    $teamRepository->expects(self::once())
      ->method('save')
      ->with(self::callback(static function (Team $team) use (&$savedTeam): bool {
        $savedTeam = $team;

        return true;
      }));

    /** @var UuidFactory&MockObject $uuidFactory */
    $uuidFactory = $this->createMock(UuidFactory::class);
    $uuidFactory->expects(self::once())
      ->method('create')
      ->with(TeamId::class)
      ->willReturn(new TeamId(self::TEAM_ID));

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(
        static fn (object $event): bool => $event instanceof TeamCreatedEvent
          && self::ORGANIZATION_ID === $event->organizationId
          && self::TEAM_ID === $event->teamId
          && 'Field crew A' === $event->name,
      ));

    $handler = new CreateTeamHandler(
      organizationRepository: $organizationRepository,
      teamRepository: $teamRepository,
      uuidFactory: $uuidFactory,
      eventDispatcher: $eventDispatcher,
    );

    $result = $handler->__invoke(new CreateTeamCommand(
      organizationId: self::ORGANIZATION_ID,
      name: 'Field crew A',
      description: 'Handles rooftops',
    ));

    self::assertInstanceOf(CreateTeamResult::class, $result);
    self::assertSame(self::TEAM_ID, $result->id);
    self::assertSame(self::ORGANIZATION_ID, $result->organizationId);
    self::assertSame('Field crew A', $result->name);
    self::assertSame('Handles rooftops', $result->description);
    self::assertInstanceOf(Team::class, $savedTeam);
  }

  #[Test]
  public function testInvokeThrowsWhenOrganizationDoesNotExist(): void
  {
    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())->method('findById')->willReturn(null);

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new CreateTeamHandler(
      organizationRepository: $organizationRepository,
      teamRepository: $this->createStub(TeamRepositoryPort::class),
      uuidFactory: $this->createStub(UuidFactory::class),
      eventDispatcher: $eventDispatcher,
    );

    $this->expectException(OrganizationNotFoundException::class);

    $handler->__invoke(new CreateTeamCommand(
      organizationId: self::ORGANIZATION_ID,
      name: 'Field crew A',
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenTeamNameAlreadyExists(): void
  {
    $organization = $this->organization();

    $existingTeam = Team::reconstitute(
      id: TeamId::fromString('550e8400-e29b-41d4-a716-446655440499'),
      organizationId: OrganizationId::fromString(self::ORGANIZATION_ID),
      name: new TeamName('Field crew A'),
      description: '',
      createdAt: new DateTimeImmutable('-1 day'),
      updatedAt: new DateTimeImmutable('-1 day'),
    );

    $organizationRepository = $this->createStub(OrganizationRepositoryPort::class);
    $organizationRepository->method('findById')->willReturn($organization);

    /** @var TeamRepositoryPort&MockObject $teamRepository */
    $teamRepository = $this->createMock(TeamRepositoryPort::class);
    $teamRepository->expects(self::once())->method('findByOrganizationAndName')->willReturn($existingTeam);
    $teamRepository->expects(self::never())->method('save');

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new CreateTeamHandler(
      organizationRepository: $organizationRepository,
      teamRepository: $teamRepository,
      uuidFactory: $this->createStub(UuidFactory::class),
      eventDispatcher: $eventDispatcher,
    );

    $this->expectException(TeamNameAlreadyExistsException::class);

    $handler->__invoke(new CreateTeamCommand(
      organizationId: self::ORGANIZATION_ID,
      name: 'Field crew A',
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
}
