<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Team\GetTeam;

use DateTimeImmutable;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase GetTeamResult.
 *
 * Read model for a single team, shared by the get and list team queries
 * (mirrors {@see \Organization\Application\UseCase\Query\Organization\ListOrganizationRoles\GetOrganizationRoleResult}).
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetTeamResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the GetTeamResult class.
   *
   * @since 1.0.0
   *
   * @param string $id the team identifier
   * @param string $organizationId the organization identifier
   * @param string $name the team name
   * @param string $description the team description
   * @param int $memberCount the number of members assigned to the team
   * @param DateTimeImmutable $createdAt the creation timestamp
   * @param DateTimeImmutable $updatedAt the last update timestamp
   */
  public function __construct(
    public string $id,
    public string $organizationId,
    public string $name,
    public string $description,
    public int $memberCount,
    public DateTimeImmutable $createdAt,
    public DateTimeImmutable $updatedAt,
  ) {
  }
  // #endregion
}
