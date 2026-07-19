<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Domain\Model\Team;

use DateTimeImmutable;
use Organization\Domain\Model\Team\Team;
use Organization\Domain\ValueObject\{OrganizationId, TeamId, TeamName};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

#[CoversClass(Team::class)]
final class TeamTest extends TestCase
{
  private const string TEAM_ID = '22222222-2222-4222-8222-222222222221';

  private const string ORGANIZATION_ID = '33333333-3333-4333-8333-333333333331';

  #[Test]
  public function testCreateExposesDefaults(): void
  {
    $team = Team::create(
      id: TeamId::fromString(self::TEAM_ID),
      organizationId: OrganizationId::fromString(self::ORGANIZATION_ID),
      name: new TeamName('Field crew A'),
    );

    self::assertSame(self::TEAM_ID, (string) $team->id());
    self::assertSame(self::ORGANIZATION_ID, (string) $team->organizationId());
    self::assertSame('Field crew A', (string) $team->name());
    self::assertSame('', $team->description());
    self::assertEquals($team->createdAt(), $team->updatedAt());
  }

  #[Test]
  public function testRenameUpdatesNameAndTouchesUpdatedAt(): void
  {
    $team = Team::create(
      id: TeamId::fromString(self::TEAM_ID),
      organizationId: OrganizationId::fromString(self::ORGANIZATION_ID),
      name: new TeamName('Field crew A'),
    );
    $originalUpdatedAt = $team->updatedAt();

    $team->rename(new TeamName('Field crew B'));

    self::assertSame('Field crew B', (string) $team->name());
    self::assertGreaterThanOrEqual($originalUpdatedAt, $team->updatedAt());
  }

  #[Test]
  public function testDescribeUpdatesDescription(): void
  {
    $team = Team::create(
      id: TeamId::fromString(self::TEAM_ID),
      organizationId: OrganizationId::fromString(self::ORGANIZATION_ID),
      name: new TeamName('Field crew A'),
    );

    $team->describe('Handles rooftop equipment inspections');

    self::assertSame('Handles rooftop equipment inspections', $team->description());
  }

  #[Test]
  public function testReconstituteRestoresState(): void
  {
    $createdAt = new DateTimeImmutable('2026-06-01T00:00:00+00:00');
    $updatedAt = new DateTimeImmutable('2026-06-02T00:00:00+00:00');

    $team = Team::reconstitute(
      id: TeamId::fromString(self::TEAM_ID),
      organizationId: OrganizationId::fromString(self::ORGANIZATION_ID),
      name: new TeamName('Field crew A'),
      description: 'desc',
      createdAt: $createdAt,
      updatedAt: $updatedAt,
    );

    self::assertSame('desc', $team->description());
    self::assertSame($createdAt, $team->createdAt());
    self::assertSame($updatedAt, $team->updatedAt());
  }
}
