<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Command\Assignment\AssignTeamToIntervention;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase AssignTeamToInterventionCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AssignTeamToInterventionCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * AssignTeamToInterventionCommand class.
   *
   * @since 1.0.0
   *
   * @param string $userId the acting user identifier
   * @param string $interventionId the intervention identifier
   * @param string $teamId the organization team identifier to assign
   * @param ?int $expectedRevision the intervention revision the caller believes it is mutating, from the `If-Match` header; null is refused by the workflow gateway with 428, exactly like a manual participants edit
   */
  public function __construct(
    public string $userId,
    public string $interventionId,
    public string $teamId,
    public ?int $expectedRevision = null,
  ) {
  }
  // #endregion
}
