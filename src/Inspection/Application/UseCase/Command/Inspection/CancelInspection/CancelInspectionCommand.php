<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Command\Inspection\CancelInspection;

use Shared\Application\Message\CommandMessage;

/**
 * Class CancelInspectionCommand
 *
 * Requests cancellation of an inspection in an organization.
 *
 * @category Command
 */
final readonly class CancelInspectionCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Identifies the organization and inspection whose cancellation is requested.
   *
   * @access public
   *
   * @param string $organizationId the owning organization identifier
   * @param string $inspectionId the inspection identifier to cancel
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
