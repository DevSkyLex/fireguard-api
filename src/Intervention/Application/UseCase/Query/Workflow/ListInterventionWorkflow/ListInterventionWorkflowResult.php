<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Query\Workflow\ListInterventionWorkflow;

use Intervention\Application\Contract\Workflow\InterventionWorkflowPage;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase ListInterventionWorkflowResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListInterventionWorkflowResult implements ResultMessage
{
  /**
   * Constructor.
   *
   * Initializes a new instance of the ListInterventionWorkflowResult class.
   *
   * @since 1.0.0
   *
   * @param InterventionWorkflowPage $page the page value
   */
  public function __construct(public InterventionWorkflowPage $page)
  {
  }
}
