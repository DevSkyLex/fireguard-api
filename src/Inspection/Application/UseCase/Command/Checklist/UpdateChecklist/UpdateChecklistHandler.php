<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Command\Checklist\UpdateChecklist;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Inspection\Application\Port\Outbound\{ChecklistRepositoryPort, InspectionRepositoryPort};
use Inspection\Domain\Exception\{ChecklistInUseException, ChecklistNotFoundException, ChecklistReferenceCodeAlreadyExistsException};
use Inspection\Domain\Model\Checklist\ChecklistItem;
use Inspection\Domain\ValueObject\{ChecklistId, ChecklistOrganizationId, InspectionOrganizationId};
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Message\CommandHandler;
use Shared\Domain\Exception\InvalidValueException;
use Throwable;

use function str_contains;
use function strtolower;

/**
 * UseCase UpdateChecklistHandler.
 *
 * Partially updates a checklist template. An archived checklist can never be
 * edited (`ChecklistArchivedException`, raised by the aggregate). Once the
 * checklist is referenced by at least one existing inspection, its item list
 * becomes structurally immutable (`ChecklistInUseException`): the aggregate
 * row is mutated in place and `Inspection` only stores a bare
 * `InspectionChecklistId` foreign key with no per-inspection snapshot of the
 * checklist content, so replacing items on an in-use checklist would silently
 * change how already-recorded inspection evidence is interpreted. Only the
 * name and reference code stay editable on an in-use checklist; changing the
 * item set requires creating a new checklist.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UpdateChecklistHandler implements CommandHandler
{
  // #region Constructor
  public function __construct(
    private ChecklistRepositoryPort $checklistRepository,
    private InspectionRepositoryPort $inspectionRepository,
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
  public function __invoke(UpdateChecklistCommand $command): UpdateChecklistResult
  {
    $checklistId = ChecklistId::fromString($command->checklistId);
    $organizationId = ChecklistOrganizationId::fromString($command->organizationId);

    if ($command->hasName && null === $command->name) {
      throw InvalidValueException::because('Field "name" cannot be null when provided.');
    }

    $checklist = $this->checklistRepository->findById($checklistId);

    if (null === $checklist || (string) $checklist->organizationId() !== (string) $organizationId) {
      throw ChecklistNotFoundException::withId($command->checklistId);
    }

    if ($command->hasItems) {
      $referencingInspections = $this->inspectionRepository->countByOrganizationId(
        InspectionOrganizationId::fromString($command->organizationId),
        checklistId: $command->checklistId,
      );

      if ($referencingInspections > 0) {
        throw ChecklistInUseException::withId($command->checklistId);
      }
    }

    $items = [];
    if ($command->hasItems) {
      foreach ($command->items ?? [] as $index => $itemData) {
        $items[] = ChecklistItem::create(
          id: $this->uuidFactory->generateRaw(),
          label: $itemData['label'],
          position: $itemData['position'] ?? $index,
          required: $itemData['required'] ?? true,
          description: $itemData['description'] ?? null,
        );
      }
    }

    $checklist->update(
      name: $command->name,
      hasName: $command->hasName,
      referenceCode: $command->referenceCode,
      hasReferenceCode: $command->hasReferenceCode,
      items: $items,
      hasItems: $command->hasItems,
    );

    try {
      $this->checklistRepository->save($checklist);
    } catch (Throwable $exception) {
      if ($this->isDuplicateReferenceCodeViolation($exception)) {
        throw ChecklistReferenceCodeAlreadyExistsException::withReferenceCode((string) $checklist->referenceCode());
      }

      throw $exception;
    }

    return new UpdateChecklistResult(
      checklistId: (string) $checklist->id(),
      organizationId: (string) $checklist->organizationId(),
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
