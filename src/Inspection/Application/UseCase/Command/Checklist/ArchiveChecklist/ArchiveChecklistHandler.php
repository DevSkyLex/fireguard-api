<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Command\Checklist\ArchiveChecklist;

use Inspection\Application\Port\Outbound\ChecklistRepositoryPort;
use Inspection\Domain\Exception\ChecklistNotFoundException;
use Inspection\Domain\ValueObject\{ChecklistId, ChecklistOrganizationId};
use Shared\Application\Message\CommandHandler;

/**
 * UseCase ArchiveChecklistHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ArchiveChecklistHandler implements CommandHandler
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
  public function __invoke(ArchiveChecklistCommand $command): ArchiveChecklistResult
  {
    $checklistId = ChecklistId::fromString($command->checklistId);
    $organizationId = ChecklistOrganizationId::fromString($command->organizationId);

    $checklist = $this->checklistRepository->findById($checklistId);

    if (null === $checklist || (string) $checklist->organizationId() !== (string) $organizationId) {
      throw ChecklistNotFoundException::withId($command->checklistId);
    }

    $checklist->archive();

    $this->checklistRepository->save($checklist);

    return new ArchiveChecklistResult(
      checklistId: (string) $checklist->id(),
      status: $checklist->status()->value,
      updatedAt: $checklist->updatedAt(),
    );
  }
  // #endregion
}
