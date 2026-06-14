<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Query\Workflow\GetInterventionWorkflow;

use Intervention\Application\Contract\Workflow\InterventionWorkflowView;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase GetInterventionWorkflowResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetInterventionWorkflowResult implements ResultMessage
{
  /**
   * Constructor.
   *
   * Initializes a new instance of the GetInterventionWorkflowResult class.
   *
   * @since 1.0.0
   *
   * @param InterventionWorkflowView $view the view value
   */
  public function __construct(public InterventionWorkflowView $view)
  {
  }
}
