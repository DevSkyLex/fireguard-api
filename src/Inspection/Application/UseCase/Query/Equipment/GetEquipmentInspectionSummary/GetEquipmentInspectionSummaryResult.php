<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Query\Equipment\GetEquipmentInspectionSummary;

use Inspection\Application\Contract\Equipment\EquipmentInspectionSummary;
use Shared\Application\Message\ResultMessage;

/**
 * Result GetEquipmentInspectionSummaryResult.
 *
 * @category UseCase
 */
final readonly class GetEquipmentInspectionSummaryResult implements ResultMessage
{
  /**
   * Method __construct.
   *
   * @param EquipmentInspectionSummary $summary the authorized equipment summary
   */
  public function __construct(public EquipmentInspectionSummary $summary)
  {
  }
}
