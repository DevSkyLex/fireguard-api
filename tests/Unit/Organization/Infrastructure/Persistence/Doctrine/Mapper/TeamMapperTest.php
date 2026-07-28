<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Infrastructure\Persistence\Doctrine\Mapper;

use DateTimeImmutable;
use LogicException;
use Organization\Domain\Model\Team\Team;
use Organization\Domain\ValueObject\{OrganizationId, TeamId, TeamName};
use Organization\Infrastructure\Persistence\Doctrine\Mapper\TeamMapper;
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationRecord, TeamRecord};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test TeamMapperTest.
 *
 * A team's owning organization is what scopes it for every permission
 * check, and it arrives through the Doctrine association rather than a
 * column. Mapping a record whose association was not hydrated would
 * produce a team belonging to nobody, so that case must fail loudly.
 *
 * @category Mapper Tests
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(TeamMapper::class)]
final class TeamMapperTest extends TestCase
{
  // #region Constants
  private const string TEAM_ID = '550e8400-e29b-41d4-a716-446655480001';

  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655480002';
  // #endregion

  // #region Methods
  #[Test]
  public function testToDomainRebuildsTheTeamWithItsOrganization(): void
  {
    $team = TeamMapper::toDomain($this->record());

    self::assertSame(self::TEAM_ID, (string) $team->id());
    self::assertSame(self::ORGANIZATION_ID, (string) $team->organizationId());
    self::assertSame('Night shift', (string) $team->name());
    self::assertSame('Covers 22:00-06:00', $team->description());
    self::assertEquals(new DateTimeImmutable('2026-01-01T00:00:00+00:00'), $team->createdAt());
    self::assertEquals(new DateTimeImmutable('2026-01-02T00:00:00+00:00'), $team->updatedAt());
  }

  #[Test]
  public function testToDomainRefusesARecordWithoutItsOrganization(): void
  {
    $record = $this->record();
    $record->organization = null;

    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('Team record must reference an organization.');

    TeamMapper::toDomain($record);
  }

  #[Test]
  public function testToRecordCopiesEveryPersistedField(): void
  {
    $record = TeamMapper::toRecord($this->team());

    self::assertSame(self::TEAM_ID, $record->id);
    self::assertSame('Night shift', $record->name);
    self::assertSame('Covers 22:00-06:00', $record->description);
    self::assertEquals(new DateTimeImmutable('2026-01-01T00:00:00+00:00'), $record->createdAt);
    self::assertEquals(new DateTimeImmutable('2026-01-02T00:00:00+00:00'), $record->updatedAt);
  }

  #[Test]
  public function testRoundTripPreservesTheTeam(): void
  {
    $restored = TeamMapper::toDomain($this->recordFrom(TeamMapper::toRecord($this->team())));

    self::assertSame((string) $this->team()->id(), (string) $restored->id());
    self::assertSame((string) $this->team()->name(), (string) $restored->name());
    self::assertSame($this->team()->description(), $restored->description());
  }

  private function recordFrom(TeamRecord $record): TeamRecord
  {
    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $record->organization = $organization;

    return $record;
  }

  private function record(): TeamRecord
  {
    $record = new TeamRecord();
    $record->id = self::TEAM_ID;
    $record->name = 'Night shift';
    $record->description = 'Covers 22:00-06:00';
    $record->createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $record->updatedAt = new DateTimeImmutable('2026-01-02T00:00:00+00:00');

    return $this->recordFrom($record);
  }

  private function team(): Team
  {
    return Team::reconstitute(
      id: TeamId::fromString(self::TEAM_ID),
      organizationId: OrganizationId::fromString(self::ORGANIZATION_ID),
      name: new TeamName('Night shift'),
      description: 'Covers 22:00-06:00',
      createdAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-01-02T00:00:00+00:00'),
    );
  }
  // #endregion
}
