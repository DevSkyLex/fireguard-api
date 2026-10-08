<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Query\Time\ListTimeEntryVersions;

use Intervention\Application\Port\Outbound\InterventionTimeEntryRepositoryPort;
use Intervention\Application\Service\InterventionTimeAccessPolicy;
use Intervention\Domain\Exception\InterventionNotFoundException;
use Shared\Application\Message\QueryHandler;

use function array_pop;
use function count;

/**
 * Class ListTimeEntryVersionsHandler
 *
 * Applies the journal's beneficiary scope before reading retained revisions.
 *
 * @category Handler
 */
final readonly class ListTimeEntryVersionsHandler implements QueryHandler
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Supplies persistence and the existing journal authorization policy.
   *
   * @access public
   *
   * @param InterventionTimeEntryRepositoryPort $entries independent journal persistence
   * @param InterventionTimeAccessPolicy $access scoped member authorization
   *
   * @return void
   */
  public function __construct(private InterventionTimeEntryRepositoryPort $entries, private InterventionTimeAccessPolicy $access)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke
   *
   * Hides foreign entries and other beneficiaries from callers without time management.
   *
   * @access public
   *
   * @param ListTimeEntryVersionsQuery $query requested scope and exclusive revision cursor
   *
   * @return ListTimeEntryVersionsResult bounded retained history with a complete count
   */
  public function __invoke(ListTimeEntryVersionsQuery $query): ListTimeEntryVersionsResult
  {
    $task = $this->entries->context($query->taskId);
    if (null === $task) {
      throw InterventionNotFoundException::withId($query->taskId);
    }
    $actor = $this->access->actor($task, $query->userId);
    $entry = $this->entries->find($query->entryId);
    if (null === $entry || $entry->workItemId !== $task->taskId || ($entry->memberId !== $actor && !$this->access->canManage($task, $query->userId))) {
      throw InterventionNotFoundException::withId($query->entryId);
    }
    $versions = $this->entries->versions($entry->id, $query->beforeRevision, $query->itemsPerPage + 1);
    $hasMore = count($versions) > $query->itemsPerPage;
    if ($hasMore) {
      array_pop($versions);
    }
    $next = $hasMore ? $versions[count($versions) - 1]->revision : null;

    return new ListTimeEntryVersionsResult($versions, $this->entries->countVersions($entry->id), $query->itemsPerPage, $next);
  }
  // #endregion
}
