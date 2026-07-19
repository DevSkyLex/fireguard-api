<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Command\Assignment\AssignTeamToIntervention;

use Intervention\Application\Contract\Workflow\InterventionWorkflowView;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase AssignTeamToInterventionResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AssignTeamToInterventionResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * AssignTeamToInterventionResult class.
   *
   * @since 1.0.0
   *
   * @param ?InterventionWorkflowView $view the updated intervention view
   */
  public function __construct(public ?InterventionWorkflowView $view)
  {
  }
  // #endregion
}
