<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Command\Workflow\MutateInterventionWorkflow;

use Intervention\Application\Contract\Workflow\InterventionWorkflowView;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase MutateInterventionWorkflowResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MutateInterventionWorkflowResult implements ResultMessage
{
  /**
   * Constructor.
   *
   * Initializes a new instance of the MutateInterventionWorkflowResult class.
   *
   * @since 1.0.0
   *
   * @param ?InterventionWorkflowView $view the view value
   */
  public function __construct(public ?InterventionWorkflowView $view)
  {
  }
}
