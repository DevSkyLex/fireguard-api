<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Team\DeleteTeam;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase DeleteTeamCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeleteTeamCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the DeleteTeamCommand class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $teamId the team identifier
   */
  public function __construct(
    public string $organizationId,
    public string $teamId,
  ) {
  }
  // #endregion
}
