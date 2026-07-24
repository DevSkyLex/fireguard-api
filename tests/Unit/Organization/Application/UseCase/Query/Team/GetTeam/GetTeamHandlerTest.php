<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\UseCase\Query\Team\GetTeam;

use Organization\Application\Port\Outbound\{OrganizationRepositoryPort, TeamRepositoryPort};
use Organization\Application\UseCase\Query\Team\GetTeam\{GetTeamHandler, GetTeamQuery};
use Organization\Domain\Exception\{OrganizationNotFoundException, TeamNotFoundException};
use Organization\Domain\Model\Organization\Organization;
use Organization\Domain\Model\Team\Team;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationName, TeamId, TeamName};
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function sprintf;

/**
 * Test GetTeamHandlerTest.
 *
 * @category Test
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class GetTeamHandlerTest extends TestCase
{
  private const string ORGANIZATION_ID = '11111111-1111-4111-8111-111111111111';

  private const string TEAM_ID = '22222222-2222-4222-8222-222222222222';

  private const string OTHER_ORGANIZATION_ID = '33333333-3333-4333-8333-333333333333';

  #[Test]
  public function itReturnsTheTeamReadModelForTheScopedOrganization(): void
  {
    $team = Team::create(
      TeamId::fromString(self::TEAM_ID),
      OrganizationId::fromString(self::ORGANIZATION_ID),
      new TeamName('Engineering'),
      'The engineering team',
    );

    $organizations = $this->createStub(OrganizationRepositoryPort::class);
    $organizations->method('findById')->willReturn($this->organization());

    $teams = $this->createStub(TeamRepositoryPort::class);
    $teams->method('findById')->willReturn($team);
    $teams->method('countMembers')->willReturn(7);

    $handler = new GetTeamHandler($organizations, $teams);

    $result = $handler(new GetTeamQuery(self::ORGANIZATION_ID, self::TEAM_ID));

    self::assertSame(self::TEAM_ID, $result->id);
    self::assertSame(self::ORGANIZATION_ID, $result->organizationId);
    self::assertSame('Engineering', $result->name);
    self::assertSame('The engineering team', $result->description);
    self::assertSame(7, $result->memberCount);
    self::assertSame($team->createdAt(), $result->createdAt);
    self::assertSame($team->updatedAt(), $result->updatedAt);
  }

  #[Test]
  public function itThrowsWhenTheOrganizationDoesNotExist(): void
  {
    $organizations = $this->createStub(OrganizationRepositoryPort::class);
    $organizations->method('findById')->willReturn(null);

    $teams = $this->createStub(TeamRepositoryPort::class);

    $handler = new GetTeamHandler($organizations, $teams);

    $this->expectException(OrganizationNotFoundException::class);
    $this->expectExceptionMessage(sprintf('Organization with ID "%s" not found.', self::ORGANIZATION_ID));

    $handler(new GetTeamQuery(self::ORGANIZATION_ID, self::TEAM_ID));
  }

  #[Test]
  public function itThrowsWhenTheTeamDoesNotExist(): void
  {
    $organizations = $this->createStub(OrganizationRepositoryPort::class);
    $organizations->method('findById')->willReturn($this->organization());

    $teams = $this->createStub(TeamRepositoryPort::class);
    $teams->method('findById')->willReturn(null);

    $handler = new GetTeamHandler($organizations, $teams);

    $this->expectException(TeamNotFoundException::class);
    $this->expectExceptionMessage(sprintf('Team with ID "%s" not found.', self::TEAM_ID));

    $handler(new GetTeamQuery(self::ORGANIZATION_ID, self::TEAM_ID));
  }

  #[Test]
  public function itThrowsWhenTheTeamBelongsToAnotherOrganization(): void
  {
    $team = Team::create(
      TeamId::fromString(self::TEAM_ID),
      OrganizationId::fromString(self::OTHER_ORGANIZATION_ID),
      new TeamName('Engineering'),
      'The engineering team',
    );

    $organizations = $this->createStub(OrganizationRepositoryPort::class);
    $organizations->method('findById')->willReturn($this->organization());

    $teams = $this->createStub(TeamRepositoryPort::class);
    $teams->method('findById')->willReturn($team);

    $handler = new GetTeamHandler($organizations, $teams);

    $this->expectException(TeamNotFoundException::class);
    $this->expectExceptionMessage(sprintf('Team with ID "%s" not found.', self::TEAM_ID));

    $handler(new GetTeamQuery(self::ORGANIZATION_ID, self::TEAM_ID));
  }

  private function organization(): Organization
  {
    return Organization::create(
      OrganizationId::fromString(self::ORGANIZATION_ID),
      new OrganizationName('Acme Corp'),
      'owner-user-id',
    );
  }
}
