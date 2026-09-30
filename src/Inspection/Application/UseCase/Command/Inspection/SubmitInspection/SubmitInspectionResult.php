<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Command\Inspection\SubmitInspection;

use DateTimeImmutable;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase SubmitInspectionResult.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class SubmitInspectionResult implements ResultMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Returns the inspection identity, resulting lifecycle status, and update time.
   *
   * @access public
   *
   * @param string $inspectionId identifier of the submitted inspection
   * @param string $status lifecycle status after submission
   * @param DateTimeImmutable $updatedAt time the inspection was updated
   *
   * @return void
   */
  public function __construct(
    public string $inspectionId,
    public string $status,
    public DateTimeImmutable $updatedAt,
  ) {
  }
  // #endregion
}
