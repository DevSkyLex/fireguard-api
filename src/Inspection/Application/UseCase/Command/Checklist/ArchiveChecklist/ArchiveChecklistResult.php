<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Command\Checklist\ArchiveChecklist;

use DateTimeImmutable;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase ArchiveChecklistResult.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ArchiveChecklistResult implements ResultMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Returns the checklist identity, resulting status, and update time after archival.
   *
   * @access public
   *
   * @param string $checklistId identifier of the archived checklist
   * @param string $status checklist status after the operation
   * @param DateTimeImmutable $updatedAt time the checklist state was updated
   *
   * @return void
   */
  public function __construct(
    public string $checklistId,
    public string $status,
    public DateTimeImmutable $updatedAt,
  ) {
  }
  // #endregion
}
