<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Command\Inspection\EditInspection;

use DateTimeImmutable;
use Shared\Application\Message\ResultMessage;

/**
 * Class EditInspectionResult
 *
 * Carries the result of the EditInspectionResult operation.
 *
 * @category UseCase
 */
final readonly class EditInspectionResult implements ResultMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Initializes the EditInspectionResult dependencies and state.
   *
   * @access public
   *
   * @param string $inspectionId the inspection identifier
   * @param string $organizationId the organization identifier
   * @param DateTimeImmutable $updatedAt the updated time
   *
   * @return void
   */
  public function __construct(
    public string $inspectionId,
    public string $organizationId,
    public DateTimeImmutable $updatedAt,
  ) {
  }
  // #endregion
}
