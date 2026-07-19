<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Team\DeleteTeam;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase DeleteTeamResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeleteTeamResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the DeleteTeamResult class.
   *
   * @since 1.0.0
   *
   * @param string $teamId the deleted team identifier
   * @param string $organizationId the organization identifier
   */
  public function __construct(
    public string $teamId,
    public string $organizationId,
  ) {
  }
  // #endregion
}
