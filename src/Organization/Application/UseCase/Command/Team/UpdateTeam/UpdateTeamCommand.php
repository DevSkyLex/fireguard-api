<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Team\UpdateTeam;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase UpdateTeamCommand.
 *
 * Every field is optional: an omitted field (null) leaves the current
 * value unchanged.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UpdateTeamCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the UpdateTeamCommand class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $teamId the team identifier
   * @param ?string $name the new team name, when set
   * @param ?string $description the new team description, when set
   */
  public function __construct(
    public string $organizationId,
    public string $teamId,
    public ?string $name = null,
    public ?string $description = null,
  ) {
  }
  // #endregion
}
