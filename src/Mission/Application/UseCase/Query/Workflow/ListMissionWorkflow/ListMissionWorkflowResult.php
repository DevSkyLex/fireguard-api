<?php

declare(strict_types=1);

namespace Mission\Application\UseCase\Query\Workflow\ListMissionWorkflow;

use Mission\Application\Contract\Workflow\MissionWorkflowPage;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase ListMissionWorkflowResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListMissionWorkflowResult implements ResultMessage
{
  /**
   * Constructor.
   *
   * Initializes a new instance of the ListMissionWorkflowResult class.
   *
   * @since 1.0.0
   *
   * @param MissionWorkflowPage $page the page value
   */
  public function __construct(public MissionWorkflowPage $page)
  {
  }
}
