<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Command\Inspection\CancelInspection;

use Shared\Application\Message\ResultMessage;

/**
 * Class CancelInspectionResult
 *
 * Carries the result of the CancelInspectionResult operation.
 *
 * @category UseCase
 */
final readonly class CancelInspectionResult implements ResultMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Initializes the CancelInspectionResult dependencies and state.
   *
   * @access public
   *
   * @param string $inspectionId the inspection identifier
   * @param string $organizationId the organization identifier
   *
   * @return void
   */
  public function __construct(
    public string $inspectionId,
    public string $organizationId,
  ) {
  }
  // #endregion
}
