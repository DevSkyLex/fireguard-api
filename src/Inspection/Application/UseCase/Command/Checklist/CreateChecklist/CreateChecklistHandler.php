<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Command\Checklist\CreateChecklist;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Inspection\Application\Port\Outbound\ChecklistRepositoryPort;
use Inspection\Domain\Exception\ChecklistReferenceCodeAlreadyExistsException;
use Inspection\Domain\Model\Checklist\{Checklist, ChecklistItem};
use Inspection\Domain\ValueObject\{ChecklistId, ChecklistOrganizationId};
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Message\CommandHandler;
use Throwable;

use function array_map;
use function str_contains;
use function strtolower;

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
      referenceCode: $command->referenceCode,
    );

    try {
      $this->checklistRepository->save($checklist);
    } catch (Throwable $exception) {
      if ($this->isDuplicateReferenceCodeViolation($exception)) {
        throw ChecklistReferenceCodeAlreadyExistsException::withReferenceCode((string) $checklist->referenceCode());
      }

      throw $exception;
    }

    return new CreateChecklistResult(
      checklistId: (string) $checklist->id(),
      organizationId: (string) $checklist->organizationId(),
      name: $checklist->name(),
      referenceCode: $checklist->referenceCode(),
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

  /**
   * Method isDuplicateReferenceCodeViolation.
   *
   * Detects whether a persistence failure was caused by the unique
   * (organization_id, reference_code) constraint.
   *
   * @since 1.0.0
   *
   * @param Throwable $exception the persistence exception
   *
   * @return bool true when the failure is caused by reference code uniqueness
   */
  private function isDuplicateReferenceCodeViolation(Throwable $exception): bool
  {
    $current = $exception;

    while (null !== $current) {
      if ($current instanceof UniqueConstraintViolationException) {
        $message = strtolower($current->getMessage());

        if (str_contains($message, 'uniq_checklist_organization_reference_code')) {
          return true;
        }
      }

      $current = $current->getPrevious();
    }

    return false;
  }
  // #endregion
}
