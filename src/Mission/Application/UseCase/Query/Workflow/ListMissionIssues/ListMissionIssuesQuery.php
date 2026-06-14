<?php

declare(strict_types=1);

namespace Mission\Application\UseCase\Query\Workflow\ListMissionIssues;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase ListMissionIssuesQuery.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListMissionIssuesQuery implements QueryMessage
{
  /**
   * Constructor.
   *
   * Initializes a new instance of the ListMissionIssuesQuery class.
   *
   * @since 1.0.0
   *
   * @param string $userId the user id value
   * @param string $missionId the mission id value
   */
  public function __construct(
    public string $userId,
    public string $missionId,
  ) {
  }
}
