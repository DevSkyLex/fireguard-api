<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Query\Activity\ListInterventionActivities;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase ListInterventionActivitiesQuery.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListInterventionActivitiesQuery implements QueryMessage
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $userId the user id value
   * @param string $interventionId the intervention id value
   * @param int $page the page value
   * @param int $itemsPerPage the items per page value
   */
  public function __construct(
    public string $userId,
    public string $interventionId,
    public int $page,
    public int $itemsPerPage,
  ) {
  }
}
