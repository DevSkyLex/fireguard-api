<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Command\Inspection\DeleteCanonicalInspection;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase DeleteCanonicalInspectionCommand.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeleteCanonicalInspectionCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Carries the inspection identifier and expected revision for an optimistic delete request.
   *
   * @access public
   *
   * @param string $inspectionId inspection to delete
   * @param int $expectedRevision revision read by the caller before deletion
   *
   * @return void
   */
  public function __construct(
    public string $inspectionId,
    public int $expectedRevision,
  ) {
  }
  // #endregion
}
