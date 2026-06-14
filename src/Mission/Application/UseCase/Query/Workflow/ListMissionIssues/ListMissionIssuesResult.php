<?php

declare(strict_types=1);

namespace Mission\Application\UseCase\Query\Workflow\ListMissionIssues;

use Mission\Application\Contract\Resource\MissionIssue;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase ListMissionIssuesResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListMissionIssuesResult implements ResultMessage
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param list<MissionIssue> $issues
   */
  public function __construct(public array $issues)
  {
  }
}
