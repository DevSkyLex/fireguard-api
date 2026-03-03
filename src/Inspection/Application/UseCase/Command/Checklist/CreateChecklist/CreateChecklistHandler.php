<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Command\Checklist\CreateChecklist;

use Inspection\Application\Port\Outbound\ChecklistRepositoryPort;
use Inspection\Domain\Model\Checklist\{Checklist, ChecklistItem};
use Inspection\Domain\ValueObject\{ChecklistId, ChecklistOrganizationId};
use InvalidArgumentException;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Message\CommandHandler;
use Shared\Domain\Exception\InvalidValueException;

use function array_map;

/**
 * UseCase CreateChecklistHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CreateChecklistHandler implements CommandHandler
{
  // #region Constructor
  public function __construct(
    private ChecklistRepositoryPort $checklistRepository,
    private UuidFactory $uuidFactory,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   */
  public function __invoke(CreateChecklistCommand $command): CreateChecklistResult
  {
    try {
      $organizationId = ChecklistOrganizationId::fromString($command->organizationId);

      /** @var ChecklistId $checklistId */
      $checklistId = $this->uuidFactory->create(ChecklistId::class);

      $items = [];
      foreach ($command->items as $index => $itemData) {
        $items[] = ChecklistItem::create(
          id: $this->uuidFactory->generateRaw(),
          label: $itemData['label'],
          position: $itemData['position'] ?? $index,
          required: $itemData['required'] ?? true,
          description: $itemData['description'] ?? null,
        );
      }

      $checklist = Checklist::create(
        id: $checklistId,
        organizationId: $organizationId,
        name: $command->name,
        version: $command->version,
        items: $items,
      );
    } catch (InvalidValueException $exception) {
      throw new InvalidArgumentException($exception->getMessage(), 0, $exception);
    }

    $this->checklistRepository->save($checklist);

    return new CreateChecklistResult(
      checklistId: (string) $checklist->id(),
      organizationId: (string) $checklist->organizationId(),
      name: $checklist->name(),
      version: $checklist->version(),
      status: $checklist->status()->value,
      items: array_map(
        static fn (ChecklistItem $item): array => [
          'id' => $item->id(),
          'label' => $item->label(),
          'description' => $item->description(),
          'required' => $item->required(),
          'position' => $item->position(),
        ],
        $checklist->items(),
      ),
      createdAt: $checklist->createdAt(),
      updatedAt: $checklist->updatedAt(),
    );
  }
  // #endregion
}
