<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Query\Activity\ListInterventionActivities;

use Intervention\Application\Contract\Workflow\InterventionWorkflowPage;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase ListInterventionActivitiesResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListInterventionActivitiesResult implements ResultMessage
{
  /**
   * Constructor.
   *
   * Initializes a new instance of the ListInterventionActivitiesResult class.
   *
   * @since 1.0.0
   *
   * @param InterventionWorkflowPage $page the page value
   */
  public function __construct(public InterventionWorkflowPage $page)
  {
  }
}
