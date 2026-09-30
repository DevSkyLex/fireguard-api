<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Command\Inspection\CloseInspection;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase CloseInspectionCommand.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CloseInspectionCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Identifies the organization-scoped inspection to close.
   *
   * @access public
   *
   * @param string $organizationId organization scope used to authorize the operation
   * @param string $inspectionId inspection to close
   *
   * @return void
   */
  public function __construct(
    public string $organizationId,
    public string $inspectionId,
  ) {
  }
  // #endregion
}
