<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Query\Time\GetTimeEntry;

use Intervention\Application\Port\Outbound\InterventionTimeEntryRepositoryPort;
use Intervention\Application\Service\InterventionTimeAccessPolicy;
use Intervention\Domain\Exception\InterventionNotFoundException;
use Shared\Application\Message\QueryHandler;

/**
 * Class GetTimeEntryHandler
 *
 * Reads current server values for review without walking every journal page.
 *
 * @category Handler
 */
final readonly class GetTimeEntryHandler implements QueryHandler
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Supplies journal persistence and its existing authorization policy.
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
   * Enforces task ownership and the same beneficiary scope as the journal list.
   *
   * @access public
   *
   * @param GetTimeEntryQuery $query requested entry and caller
   *
   * @return GetTimeEntryResult bounded current entry
   */
  public function __invoke(GetTimeEntryQuery $query): GetTimeEntryResult
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

    return new GetTimeEntryResult($entry);
  }
  // #endregion
}
