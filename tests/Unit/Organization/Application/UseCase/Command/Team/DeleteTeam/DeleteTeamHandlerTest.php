<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\UseCase\Command\Team\DeleteTeam;

use Organization\Application\Port\Outbound\{OrganizationRepositoryPort, TeamRepositoryPort};
use Organization\Application\UseCase\Command\Team\DeleteTeam\{
  DeleteTeamCommand,
  DeleteTeamHandler
};
use Organization\Domain\Event\Team\TeamDeletedEvent;
use Organization\Domain\Exception\{OrganizationNotFoundException, TeamNotFoundException};
use Organization\Domain\Model\Organization\Organization;
use Organization\Domain\Model\Team\Team;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationName, TeamId, TeamName};
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\EventDispatcherPort;

/**
 * Test DeleteTeamHandlerTest.
 *
 * @category Test
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class DeleteTeamHandlerTest extends TestCase
{
  private const string ORGANIZATION_ID = '11111111-1111-4111-8111-111111111111';

  private const string TEAM_ID = '22222222-2222-4222-8222-222222222222';

  private const string OTHER_ORGANIZATION_ID = '99999999-9999-4999-8999-999999999999';

  #[Test]
  public function itRemovesTheTeamDispatchesEventAndReturnsResult(): void
  {
    $team = $this->team();

    $organizations = $this->createStub(OrganizationRepositoryPort::class);
    $organizations->method('findById')->willReturn($this->organization());

    $teams = $this->createMock(TeamRepositoryPort::class);
    $teams->method('findById')->willReturn($team);
    $teams->expects(self::once())->method('remove')->with($team);

    $events = $this->createMock(EventDispatcherPort::class);
    $events->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (object $event): bool => $event instanceof TeamDeletedEvent
        && self::ORGANIZATION_ID === $event->organizationId
        && self::TEAM_ID === $event->teamId));

    $handler = new DeleteTeamHandler($organizations, $teams, $events);

    $result = $handler(new DeleteTeamCommand(self::ORGANIZATION_ID, self::TEAM_ID));

    self::assertSame(self::TEAM_ID, $result->teamId);
    self::assertSame(self::ORGANIZATION_ID, $result->organizationId);
  }

  #[Test]
  public function itThrowsWhenOrganizationDoesNotExist(): void
  {
    $organizations = $this->createStub(OrganizationRepositoryPort::class);
    $organizations->method('findById')->willReturn(null);

    $teams = $this->createMock(TeamRepositoryPort::class);
    $teams->expects(self::never())->method('findById');
    $teams->expects(self::never())->method('remove');

    $events = $this->createMock(EventDispatcherPort::class);
    $events->expects(self::never())->method('dispatch');

    $handler = new DeleteTeamHandler($organizations, $teams, $events);

    $this->expectException(OrganizationNotFoundException::class);

    $handler(new DeleteTeamCommand(self::ORGANIZATION_ID, self::TEAM_ID));
  }

  #[Test]
  public function itThrowsWhenTeamDoesNotExist(): void
  {
    $organizations = $this->createStub(OrganizationRepositoryPort::class);
    $organizations->method('findById')->willReturn($this->organization());

    $teams = $this->createMock(TeamRepositoryPort::class);
    $teams->method('findById')->willReturn(null);
    $teams->expects(self::never())->method('remove');

    $events = $this->createMock(EventDispatcherPort::class);
    $events->expects(self::never())->method('dispatch');

    $handler = new DeleteTeamHandler($organizations, $teams, $events);

    $this->expectException(TeamNotFoundException::class);

    $handler(new DeleteTeamCommand(self::ORGANIZATION_ID, self::TEAM_ID));
  }

  #[Test]
  public function itThrowsWhenTeamBelongsToAnotherOrganization(): void
  {
    $organizations = $this->createStub(OrganizationRepositoryPort::class);
    $organizations->method('findById')->willReturn($this->organization());

    $teams = $this->createMock(TeamRepositoryPort::class);
    $teams->method('findById')->willReturn($this->team(self::OTHER_ORGANIZATION_ID));
    $teams->expects(self::never())->method('remove');

    $events = $this->createMock(EventDispatcherPort::class);
    $events->expects(self::never())->method('dispatch');

    $handler = new DeleteTeamHandler($organizations, $teams, $events);

    $this->expectException(TeamNotFoundException::class);

    $handler(new DeleteTeamCommand(self::ORGANIZATION_ID, self::TEAM_ID));
  }

  private function organization(): Organization
  {
    return Organization::create(
      OrganizationId::fromString(self::ORGANIZATION_ID),
      new OrganizationName('Acme Inc.'),
      'owner-user-id',
    );
  }

  private function team(string $organizationId = self::ORGANIZATION_ID): Team
  {
    return Team::create(
      TeamId::fromString(self::TEAM_ID),
      OrganizationId::fromString($organizationId),
      new TeamName('Engineering'),
    );
  }
}
