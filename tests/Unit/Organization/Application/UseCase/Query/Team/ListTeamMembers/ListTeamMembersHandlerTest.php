<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\UseCase\Query\Team\ListTeamMembers;

use DateTimeImmutable;
use Organization\Application\Port\Outbound\{OrganizationRepositoryPort, TeamRepositoryPort};
use Organization\Application\UseCase\Query\Team\ListTeamMembers\{ListTeamMembersHandler, ListTeamMembersQuery};
use Organization\Domain\Exception\{OrganizationNotFoundException, TeamNotFoundException};
use Organization\Domain\Model\Organization\Organization;
use Organization\Domain\Model\Team\Team;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationName, TeamId, TeamName};
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Test ListTeamMembersHandlerTest.
 *
 * @category Test
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ListTeamMembersHandlerTest extends TestCase
{
  private const string ORGANIZATION_ID = '11111111-1111-4111-8111-111111111111';

  private const string OTHER_ORGANIZATION_ID = '22222222-2222-4222-8222-222222222222';

  private const string TEAM_ID = '33333333-3333-4333-8333-333333333333';

  #[Test]
  public function itListsTeamMembershipsForTheTeam(): void
  {
    $lead = new DateTimeImmutable('2026-01-01 10:00:00');
    $member = new DateTimeImmutable('2026-02-02 12:30:00');

    $organizations = $this->createStub(OrganizationRepositoryPort::class);
    $organizations->method('findById')->willReturn($this->organization());

    $teams = $this->createStub(TeamRepositoryPort::class);
    $teams->method('findById')->willReturn($this->team(self::ORGANIZATION_ID));
    $teams->method('findMemberships')->willReturn([
      ['memberId' => 'member-1', 'role' => 'lead', 'addedAt' => $lead],
      ['memberId' => 'member-2', 'role' => null, 'addedAt' => $member],
    ]);

    $handler = new ListTeamMembersHandler($organizations, $teams);

    $result = $handler(new ListTeamMembersQuery(self::ORGANIZATION_ID, self::TEAM_ID));

    self::assertCount(2, $result->memberships);

    self::assertSame('member-1', $result->memberships[0]->memberId);
    self::assertSame('lead', $result->memberships[0]->role);
    self::assertSame($lead, $result->memberships[0]->addedAt);

    self::assertSame('member-2', $result->memberships[1]->memberId);
    self::assertNull($result->memberships[1]->role);
    self::assertSame($member, $result->memberships[1]->addedAt);
  }

  #[Test]
  public function itReturnsAnEmptyListWhenTheTeamHasNoMembers(): void
  {
    $organizations = $this->createStub(OrganizationRepositoryPort::class);
    $organizations->method('findById')->willReturn($this->organization());

    $teams = $this->createStub(TeamRepositoryPort::class);
    $teams->method('findById')->willReturn($this->team(self::ORGANIZATION_ID));
    $teams->method('findMemberships')->willReturn([]);

    $handler = new ListTeamMembersHandler($organizations, $teams);

    $result = $handler(new ListTeamMembersQuery(self::ORGANIZATION_ID, self::TEAM_ID));

    self::assertSame([], $result->memberships);
  }

  #[Test]
  public function itThrowsWhenTheOrganizationDoesNotExist(): void
  {
    $organizations = $this->createStub(OrganizationRepositoryPort::class);
    $organizations->method('findById')->willReturn(null);

    $teams = $this->createMock(TeamRepositoryPort::class);
    $teams->expects(self::never())->method('findById');

    $handler = new ListTeamMembersHandler($organizations, $teams);

    $this->expectException(OrganizationNotFoundException::class);
    $this->expectExceptionMessage('Organization with ID "' . self::ORGANIZATION_ID . '" not found.');

    $handler(new ListTeamMembersQuery(self::ORGANIZATION_ID, self::TEAM_ID));
  }

  #[Test]
  public function itThrowsWhenTheTeamDoesNotExist(): void
  {
    $organizations = $this->createStub(OrganizationRepositoryPort::class);
    $organizations->method('findById')->willReturn($this->organization());

    $teams = $this->createStub(TeamRepositoryPort::class);
    $teams->method('findById')->willReturn(null);

    $handler = new ListTeamMembersHandler($organizations, $teams);

    $this->expectException(TeamNotFoundException::class);
    $this->expectExceptionMessage('Team with ID "' . self::TEAM_ID . '" not found.');

    $handler(new ListTeamMembersQuery(self::ORGANIZATION_ID, self::TEAM_ID));
  }

  #[Test]
  public function itThrowsWhenTheTeamBelongsToAnotherOrganization(): void
  {
    $organizations = $this->createStub(OrganizationRepositoryPort::class);
    $organizations->method('findById')->willReturn($this->organization());

    $teams = $this->createStub(TeamRepositoryPort::class);
    $teams->method('findById')->willReturn($this->team(self::OTHER_ORGANIZATION_ID));

    $handler = new ListTeamMembersHandler($organizations, $teams);

    $this->expectException(TeamNotFoundException::class);
    $this->expectExceptionMessage('Team with ID "' . self::TEAM_ID . '" not found.');

    $handler(new ListTeamMembersQuery(self::ORGANIZATION_ID, self::TEAM_ID));
  }

  private function organization(): Organization
  {
    return Organization::create(
      OrganizationId::fromString(self::ORGANIZATION_ID),
      new OrganizationName('Acme'),
      'owner-user',
    );
  }

  private function team(string $organizationId): Team
  {
    return Team::create(
      TeamId::fromString(self::TEAM_ID),
      OrganizationId::fromString($organizationId),
      new TeamName('Engineering'),
    );
  }
}
