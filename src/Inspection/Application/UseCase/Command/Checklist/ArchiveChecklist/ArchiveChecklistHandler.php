<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Command\Checklist\ArchiveChecklist;

use Inspection\Application\Port\Outbound\{ChecklistLockPort, ChecklistRepositoryPort};
use Inspection\Domain\Exception\ChecklistNotFoundException;
use Inspection\Domain\ValueObject\{ChecklistId, ChecklistOrganizationId};
use Shared\Application\Message\CommandHandler;

/**
 * UseCase ArchiveChecklistHandler.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ArchiveChecklistHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Provides the checklist lock and repository used to serialize and persist archival.
   *
   * @access public
   *
   * @param ChecklistLockPort $locks serializes updates for the checklist
   * @param ChecklistRepositoryPort $checklistRepository loads and saves checklist aggregates
   *
   * @return void
   */
  public function __construct(
    private ChecklistLockPort $locks,
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
    return $this->locks->withLock($command->organizationId, $command->checklistId, fn (): ArchiveChecklistResult => $this->execute($command));
  }

  /**
   * Method execute.
   *
   * Archives the checklist after confirming it belongs to the requested organization.
   *
   * @access private
   *
   * @param ArchiveChecklistCommand $command the organization and checklist identifiers
   *
   * @return ArchiveChecklistResult the archive outcome
   *
   * @throws ChecklistNotFoundException when the checklist is absent or belongs to another organization
   */
  private function execute(ArchiveChecklistCommand $command): ArchiveChecklistResult
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
