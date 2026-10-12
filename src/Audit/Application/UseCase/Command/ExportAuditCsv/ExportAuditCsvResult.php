<?php

declare(strict_types=1);

namespace Audit\Application\UseCase\Command\ExportAuditCsv;

use Shared\Application\Message\ResultMessage;

/**
 * Keeps exact binary-safe CSV bytes and the original download name in the receipt.
 *
 * @category Result
 */
final readonly class ExportAuditCsvResult implements ResultMessage
{
  /**
   * @param string $csvBase64 original CSV bytes encoded for the application JSON schema
   * @param string $fileName original successful download name
   * @param int $total matched ledger row count
   */
  public function __construct(public string $csvBase64, public string $fileName, public int $total)
  {
  }
}
