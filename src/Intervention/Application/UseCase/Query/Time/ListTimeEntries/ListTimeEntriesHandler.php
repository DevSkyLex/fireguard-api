<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Query\Time\ListTimeEntries;

/**
 * ListTimeEntriesHandler.
 *
 * @category Intervention
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListTimeEntriesHandler implements \Shared\Application\Message\QueryHandler
{
  /**
   * @since 1.0.0
   *
   * @param \Intervention\Application\Port\Outbound\InterventionTimeEntryRepositoryPort $entries persistence port for the independent time journal
   * @param \Intervention\Application\Service\InterventionTimeAccessPolicy $access authorizes journal operations for the current contributor
   */
  public function __construct(private \Intervention\Application\Port\Outbound\InterventionTimeEntryRepositoryPort $entries, private \Intervention\Application\Service\InterventionTimeAccessPolicy $access)
  {
  }

  /**
   * Returns only the journal contributions the caller is authorized to read.
   *
   * @since 1.0.0
   *
   * @param ListTimeEntriesQuery $query requested read scope and caller context
   *
   * @return ListTimeEntriesResult authorized task contributions and journal access capabilities
   */
  public function __invoke(ListTimeEntriesQuery $query): ListTimeEntriesResult
  {
    $task = $this->entries->context($query->taskId);
    if (null === $task) {
      throw \Intervention\Domain\Exception\InterventionNotFoundException::withId($query->taskId);
    }
    $actor = $this->access->actor($task, $query->userId);

    return new ListTimeEntriesResult($this->entries->list($query->taskId, $this->access->canManage($task, $query->userId) ? null : $actor));
  }
}
