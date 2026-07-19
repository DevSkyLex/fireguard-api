<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Team\UpdateTeam;

use DateTimeImmutable;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase UpdateTeamResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UpdateTeamResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the UpdateTeamResult class.
   *
   * @since 1.0.0
   *
   * @param string $id the team identifier
   * @param string $organizationId the organization identifier
   * @param string $name the team name
   * @param string $description the team description
   * @param DateTimeImmutable $createdAt the creation timestamp
   * @param DateTimeImmutable $updatedAt the last update timestamp
   */
  public function __construct(
    public string $id,
    public string $organizationId,
    public string $name,
    public string $description,
    public DateTimeImmutable $createdAt,
    public DateTimeImmutable $updatedAt,
  ) {
  }
  // #endregion
}
