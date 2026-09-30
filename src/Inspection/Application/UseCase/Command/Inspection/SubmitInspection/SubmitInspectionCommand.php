<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Command\Inspection\SubmitInspection;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase SubmitInspectionCommand.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class SubmitInspectionCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Identifies the organization-scoped inspection to submit.
   *
   * @access public
   *
   * @param string $organizationId organization scope used to authorize the operation
   * @param string $inspectionId inspection to submit
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
