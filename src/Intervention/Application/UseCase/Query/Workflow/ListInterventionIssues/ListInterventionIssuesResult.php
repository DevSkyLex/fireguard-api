<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Query\Workflow\ListInterventionIssues;

use Intervention\Application\Contract\Resource\InterventionIssue;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase ListInterventionIssuesResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListInterventionIssuesResult implements ResultMessage
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param list<InterventionIssue> $issues
   */
  public function __construct(public array $issues)
  {
  }
}
