<?php

declare(strict_types=1);

namespace Mission\Application\UseCase\Command\Workflow\MutateMissionWorkflow;

use Mission\Application\Contract\Workflow\MissionWorkflowView;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase MutateMissionWorkflowResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MutateMissionWorkflowResult implements ResultMessage
{
  /**
   * Constructor.
   *
   * Initializes a new instance of the MutateMissionWorkflowResult class.
   *
   * @since 1.0.0
   *
   * @param ?MissionWorkflowView $view the view value
   */
  public function __construct(public ?MissionWorkflowView $view)
  {
  }
}
