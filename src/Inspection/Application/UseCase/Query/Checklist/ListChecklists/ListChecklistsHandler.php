<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Query\Checklist\ListChecklists;

use Inspection\Application\Port\Outbound\ChecklistRepositoryPort;
use Inspection\Domain\ValueObject\{ChecklistOrganizationId, ChecklistStatus};
use InvalidArgumentException;
use Shared\Application\Contract\Pagination\PaginatedResult;
use Shared\Application\Message\QueryHandler;
use Shared\Domain\Exception\InvalidValueException;
use ValueError;

/**
 * UseCase ListChecklistsHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListChecklistsHandler implements QueryHandler
{
  // #region Constructor
  public function __construct(
    private ChecklistRepositoryPort $checklistRepository,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * L1.10b: the list path never hydrates each checklist's `items` array. It
   * carries only `itemCount`, resolved via a single grouped count query
   * (`countItemsGroupedByChecklistId`) instead of materializing a
   * `ChecklistItemResult` per item per row. The single-GET use case
   * (`GetChecklist\GetChecklistHandler`) is unaffected and still returns the
   * full item list.
   *
   * @since 1.0.0
   *
   * @return PaginatedResult<ListChecklistResult>
   */
  public function __invoke(ListChecklistsQuery $query): PaginatedResult
  {
    try {
      $organizationId = ChecklistOrganizationId::fromString($query->organizationId);
      $status = null !== $query->status ? ChecklistStatus::from($query->status)->value : null;
    } catch (InvalidValueException|ValueError $exception) {
      throw new InvalidArgumentException($exception->getMessage(), 0, $exception);
    }

    $checklists = $this->checklistRepository->findByOrganizationId(
      $organizationId,
      $status,
      $query->search,
      $query->sorting,
      $query->pagination->limit,
      $query->pagination->offset,
    );

    $checklistIds = [];
    foreach ($checklists as $checklist) {
      $checklistIds[] = (string) $checklist->id();
    }

    $itemCounts = $this->checklistRepository->countItemsGroupedByChecklistId($organizationId, $checklistIds);

    $results = [];

    foreach ($checklists as $checklist) {
      $checklistId = (string) $checklist->id();

      $results[] = new ListChecklistResult(
        checklistId: $checklistId,
        organizationId: (string) $checklist->organizationId(),
        name: $checklist->name(),
        version: $checklist->version(),
        status: $checklist->status()->value,
        itemCount: $itemCounts[$checklistId] ?? 0,
        createdAt: $checklist->createdAt(),
        updatedAt: $checklist->updatedAt(),
        referenceCode: $checklist->referenceCode(),
      );
    }

    $total = $this->checklistRepository->countByOrganizationId($organizationId, $status, $query->search);

    return new PaginatedResult(
      items: $results,
      total: $total,
      limit: $query->pagination->limit,
      offset: $query->pagination->offset,
    );
  }
  // #endregion
}
