<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Query\Workflow\ListInterventionWorkflow;

use Shared\Application\Contract\Sorting\{SortDirection, Sorting};
use Shared\Application\Message\QueryMessage;

/**
 * UseCase ListInterventionWorkflowQuery.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListInterventionWorkflowQuery implements QueryMessage
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $userId the user id value
   * @param string $resource the resource value
   * @param string $scopeId the scope id value
   * @param array<string, mixed> $filters
   * @param int $page the page value
   * @param int $itemsPerPage the items per page value
   * @param Sorting $sorting the requested ordering
   */
  public function __construct(
    public string $userId,
    public string $resource,
    public string $scopeId,
    public array $filters,
    public int $page,
    public int $itemsPerPage,
    public Sorting $sorting = new Sorting('updatedAt', SortDirection::DESC),
  ) {
  }
}
