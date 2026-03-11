<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Query\Checklist\ListChecklists;

use Inspection\Application\Port\Outbound\ChecklistRepositoryPort;
use Inspection\Application\UseCase\Query\Checklist\GetChecklist\{ChecklistItemResult, GetChecklistResult};
use Inspection\Domain\ValueObject\{ChecklistOrganizationId, ChecklistStatus};
use InvalidArgumentException;
use Shared\Application\Contract\Pagination\PaginatedResult;
use Shared\Application\Message\QueryHandler;
use Shared\Domain\Exception\InvalidValueException;
use ValueError;

use function count;

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
   * @since 1.0.0
   *
   * @return PaginatedResult<GetChecklistResult>
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
    );

    $results = [];

    foreach ($checklists as $checklist) {
      $items = [];

      foreach ($checklist->items() as $item) {
        $items[] = new ChecklistItemResult(
          itemId: $item->id(),
          label: $item->label(),
          position: $item->position(),
          required: $item->required(),
          description: $item->description(),
        );
      }

      $results[] = new GetChecklistResult(
        checklistId: (string) $checklist->id(),
        organizationId: (string) $checklist->organizationId(),
        name: $checklist->name(),
        version: $checklist->version(),
        status: $checklist->status()->value,
        items: $items,
        createdAt: $checklist->createdAt(),
        updatedAt: $checklist->updatedAt(),
      );
    }

    $total = count($results);

    return new PaginatedResult(
      items: $results,
      total: $total,
      limit: $total,
      offset: 0,
    );
  }
  // #endregion
}
