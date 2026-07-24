<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Query\Recurrence\ListInterventionRecurrences;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase ListInterventionRecurrencesQuery.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListInterventionRecurrencesQuery implements QueryMessage
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $userId the acting user id value
   * @param string $organizationId the organization id value
   * @param int $page the page value
   * @param int $itemsPerPage the items per page value
   * @param ?bool $isActive an optional active-state filter
   */
  public function __construct(
    public string $userId,
    public string $organizationId,
    public int $page = 1,
    public int $itemsPerPage = 30,
    public ?bool $isActive = null,
  ) {
  }
}
