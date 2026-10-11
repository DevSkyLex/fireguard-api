<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Query\ExportEquipmentReport;

use Shared\Application\Message\ResultMessage;

/** Carries rendered report bytes and their download name. @category UseCase @version 1.0.0 @author Valentin FORTIN <contact@valentin-fortin.pro> */
final readonly class ExportEquipmentReportResult implements ResultMessage
{
  /**
   * @since 1.0.0
   */
  public function __construct(public string $contents, public string $fileName)
  {
  }
}
