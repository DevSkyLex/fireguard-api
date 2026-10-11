<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Query\ExportEquipmentReport;

use Shared\Application\Message\QueryMessage;

/** Requests an entitled equipment report. @category UseCase @version 1.0.0 @author Valentin FORTIN <contact@valentin-fortin.pro> */
final readonly class ExportEquipmentReportQuery implements QueryMessage
{
  /**
   * @since 1.0.0
   */
  public function __construct(public string $userId, public string $organizationId, public string $equipmentId)
  {
  }
}
