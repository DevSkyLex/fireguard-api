<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Query\Workflow\ListInterventionIssues;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase ListInterventionIssuesQuery.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListInterventionIssuesQuery implements QueryMessage
{
  /**
   * Constructor.
   *
   * Initializes a new instance of the ListInterventionIssuesQuery class.
   *
   * @since 1.0.0
   *
   * @param string $userId the user id value
   * @param string $interventionId the intervention id value
   */
  public function __construct(
    public string $userId,
    public string $interventionId,
  ) {
  }
}
