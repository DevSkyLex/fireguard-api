<?php

declare(strict_types=1);

namespace Mission\Application\UseCase\Query\Workflow\GetMissionWorkflow;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase GetMissionWorkflowQuery.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetMissionWorkflowQuery implements QueryMessage
{
  /**
   * Constructor.
   *
   * Initializes a new instance of the GetMissionWorkflowQuery class.
   *
   * @since 1.0.0
   *
   * @param string $userId the user id value
   * @param string $resource the resource value
   * @param string $id the id value
   */
  public function __construct(
    public string $userId,
    public string $resource,
    public string $id,
  ) {
  }
}
