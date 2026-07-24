<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\UseCase\Query\Team\ListTeams;

use DateTimeImmutable;
use Organization\Application\Port\Outbound\{OrganizationRepositoryPort, TeamRepositoryPort};
use Organization\Application\UseCase\Query\Team\GetTeam\GetTeamResult;
use Organization\Application\UseCase\Query\Team\ListTeams\{
  ListTeamsHandler,
  ListTeamsQuery,
  ListTeamsResult
};
use Organization\Domain\Exception\OrganizationNotFoundException;
use Organization\Domain\Model\Organization\Organization;
use Organization\Domain\Model\Team\Team;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationName, TeamId, TeamName};
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Test ListTeamsHandlerTest.
 *
 * @category Test
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ListTeamsHandlerTest extends TestCase
{
  private const string ORGANIZATION_ID = '11111111-1111-4111-8111-111111111111';

  private const string TEAM_ID_ONE = '22222222-2222-4222-8222-222222222222';

  private const string TEAM_ID_TWO = '33333333-3333-4333-8333-333333333333';

  #[Test]
  public function itReturnsMappedTeamsForTheOrganization(): void
  {
    $createdOne = new DateTimeImmutable('-3 days');
    $updatedOne = new DateTimeImmutable('-1 day');
    $createdTwo = new DateTimeImmutable('-2 days');
    $updatedTwo = new DateTimeImmutable('-12 hours');

    $teamOne = Team::reconstitute(
      id: TeamId::fromString(self::TEAM_ID_ONE),
      organizationId: OrganizationId::fromString(self::ORGANIZATION_ID),
      name: new TeamName('Engineering'),
      description: 'Builds things',
      createdAt: $createdOne,
      updatedAt: $updatedOne,
    );
    $teamTwo = Team::reconstitute(
      id: TeamId::fromString(self::TEAM_ID_TWO),
      organizationId: OrganizationId::fromString(self::ORGANIZATION_ID),
      name: new TeamName('Design'),
      description: '',
      createdAt: $createdTwo,
      updatedAt: $updatedTwo,
    );

    $organizations = $this->createStub(OrganizationRepositoryPort::class);
    $organizations->method('findById')->willReturn($this->organization());

    $teams = $this->createMock(TeamRepositoryPort::class);
    $teams->expects(self::once())
      ->method('findByOrganizationId')
      ->willReturn([$teamOne, $teamTwo]);
    $teams->expects(self::exactly(2))
      ->method('countMembers')
      ->willReturnOnConsecutiveCalls(3, 5);

    $handler = new ListTeamsHandler($organizations, $teams);

    $result = $handler(new ListTeamsQuery(self::ORGANIZATION_ID));

    self::assertInstanceOf(ListTeamsResult::class, $result);
    self::assertCount(2, $result->teams);

    $first = $result->teams[0];
    self::assertInstanceOf(GetTeamResult::class, $first);
    self::assertSame(self::TEAM_ID_ONE, $first->id);
    self::assertSame(self::ORGANIZATION_ID, $first->organizationId);
    self::assertSame('Engineering', $first->name);
    self::assertSame('Builds things', $first->description);
    self::assertSame(3, $first->memberCount);
    self::assertSame($createdOne, $first->createdAt);
    self::assertSame($updatedOne, $first->updatedAt);

    $second = $result->teams[1];
    self::assertInstanceOf(GetTeamResult::class, $second);
    self::assertSame(self::TEAM_ID_TWO, $second->id);
    self::assertSame('Design', $second->name);
    self::assertSame('', $second->description);
    self::assertSame(5, $second->memberCount);
    self::assertSame($createdTwo, $second->createdAt);
    self::assertSame($updatedTwo, $second->updatedAt);
  }

  #[Test]
  public function itReturnsAnEmptyListWhenTheOrganizationHasNoTeams(): void
  {
    $organizations = $this->createStub(OrganizationRepositoryPort::class);
    $organizations->method('findById')->willReturn($this->organization());

    $teams = $this->createMock(TeamRepositoryPort::class);
    $teams->expects(self::once())->method('findByOrganizationId')->willReturn([]);
    $teams->expects(self::never())->method('countMembers');

    $handler = new ListTeamsHandler($organizations, $teams);

    $result = $handler(new ListTeamsQuery(self::ORGANIZATION_ID));

    self::assertInstanceOf(ListTeamsResult::class, $result);
    self::assertSame([], $result->teams);
  }

  #[Test]
  public function itThrowsWhenOrganizationDoesNotExist(): void
  {
    $organizations = $this->createStub(OrganizationRepositoryPort::class);
    $organizations->method('findById')->willReturn(null);

    $teams = $this->createMock(TeamRepositoryPort::class);
    $teams->expects(self::never())->method('findByOrganizationId');
    $teams->expects(self::never())->method('countMembers');

    $handler = new ListTeamsHandler($organizations, $teams);

    $this->expectException(OrganizationNotFoundException::class);

    $handler(new ListTeamsQuery(self::ORGANIZATION_ID));
  }

  private function organization(): Organization
  {
    return Organization::create(
      OrganizationId::fromString(self::ORGANIZATION_ID),
      new OrganizationName('Acme Inc.'),
      'owner-user-id',
    );
  }
}
