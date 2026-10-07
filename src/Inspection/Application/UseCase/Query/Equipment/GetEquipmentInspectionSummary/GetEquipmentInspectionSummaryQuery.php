<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Query\Equipment\GetEquipmentInspectionSummary;

use Shared\Application\Message\QueryMessage;

/**
 * Query GetEquipmentInspectionSummaryQuery.
 *
 * @category UseCase
 */
final readonly class GetEquipmentInspectionSummaryQuery implements QueryMessage
{
  /**
   * Method __construct.
   *
   * @param string $organizationId the requested organization
   * @param string $equipmentId the requested equipment
   * @param string $userId the authenticated reader
   */
  public function __construct(public string $organizationId, public string $equipmentId, public string $userId)
  {
  }
}
