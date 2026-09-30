<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Command\Checklist\ArchiveChecklist;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase ArchiveChecklistCommand.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ArchiveChecklistCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Identifies the organization and checklist to archive.
   *
   * @access public
   *
   * @param string $organizationId organization owning the checklist
   * @param string $checklistId checklist to archive
   *
   * @return void
   */
  public function __construct(
    public string $organizationId,
    public string $checklistId,
  ) {
  }
  // #endregion
}
