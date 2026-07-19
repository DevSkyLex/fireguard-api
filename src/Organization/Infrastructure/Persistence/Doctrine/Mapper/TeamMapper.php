<?php

declare(strict_types=1);

namespace Organization\Infrastructure\Persistence\Doctrine\Mapper;

use LogicException;
use Organization\Domain\Model\Team\Team;
use Organization\Domain\ValueObject\{OrganizationId, TeamId, TeamName};
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationRecord, TeamRecord};

/**
 * Mapper TeamMapper.
 *
 * @category Mapper
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class TeamMapper
{
  // #region Methods
  /**
   * Method toDomain.
   *
   * Maps a Doctrine team record to a domain aggregate.
   *
   * @since 1.0.0
   *
   * @param TeamRecord $record the persistence record
   *
   * @return Team the domain aggregate
   */
  public static function toDomain(TeamRecord $record): Team
  {
    if (!$record->organization instanceof OrganizationRecord) {
      throw new LogicException('Team record must reference an organization.');
    }

    return Team::reconstitute(
      id: TeamId::fromString($record->id),
      organizationId: OrganizationId::fromString($record->organization->id),
      name: new TeamName($record->name),
      description: $record->description,
      createdAt: $record->createdAt,
      updatedAt: $record->updatedAt,
    );
  }

  /**
   * Method toRecord.
   *
   * Maps a team domain aggregate to a Doctrine record.
   *
   * @since 1.0.0
   *
   * @param Team $team the domain aggregate
   *
   * @return TeamRecord the persistence record
   */
  public static function toRecord(Team $team): TeamRecord
  {
    $record = new TeamRecord();
    $record->id = (string) $team->id();
    $record->name = (string) $team->name();
    $record->description = $team->description();
    $record->createdAt = $team->createdAt();
    $record->updatedAt = $team->updatedAt();

    return $record;
  }
  // #endregion
}
