<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Team\CreateTeam;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase CreateTeamCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CreateTeamCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the CreateTeamCommand class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $name the team name
   * @param ?string $description the team description
   */
  public function __construct(
    public string $organizationId,
    public string $name,
    public ?string $description = null,
  ) {
  }
  // #endregion
}
