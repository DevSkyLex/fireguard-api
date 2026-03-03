<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Query\Checklist\GetChecklist;

use Inspection\Application\Port\Outbound\ChecklistRepositoryPort;
use Inspection\Domain\Exception\ChecklistNotFoundException;
use Inspection\Domain\ValueObject\{ChecklistId, ChecklistOrganizationId};
use InvalidArgumentException;
use Shared\Application\Message\QueryHandler;
use Shared\Domain\Exception\InvalidValueException;

/**
 * UseCase GetChecklistHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetChecklistHandler implements QueryHandler
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
   */
  public function __invoke(GetChecklistQuery $query): GetChecklistResult
  {
    try {
      $checklistId = ChecklistId::fromString($query->checklistId);
      $organizationId = ChecklistOrganizationId::fromString($query->organizationId);
    } catch (InvalidValueException $exception) {
      throw new InvalidArgumentException($exception->getMessage(), 0, $exception);
    }

    $checklist = $this->checklistRepository->findById($checklistId);

    if (null === $checklist || (string) $checklist->organizationId() !== (string) $organizationId) {
      throw ChecklistNotFoundException::withId($query->checklistId);
    }

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

    return new GetChecklistResult(
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
  // #endregion
}
