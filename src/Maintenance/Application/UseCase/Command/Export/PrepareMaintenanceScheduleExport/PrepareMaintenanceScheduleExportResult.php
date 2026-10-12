<?php

declare(strict_types=1);

namespace Maintenance\Application\UseCase\Command\Export\PrepareMaintenanceScheduleExport;

use InvalidArgumentException;
use Shared\Application\Message\ResultMessage;

use function base64_decode;

/**
 * Class PrepareMaintenanceScheduleExportResult
 *
 * Preserves original CSV bytes and filename in a scalar immutable receipt.
 *
 * @category UseCase
 */
final readonly class PrepareMaintenanceScheduleExportResult implements ResultMessage
{
  /**
   * @param string $base64Contents original binary-safe export bytes
   * @param string $fileName original download filename
   */
  public function __construct(public string $base64Contents, public string $fileName)
  {
    if (false === base64_decode($base64Contents, true)) {
      throw new InvalidArgumentException('The maintenance CSV snapshot is not valid base64.');
    }
  }

  /**
   * @return string exact original CSV bytes
   */
  public function contents(): string
  {
    $contents = base64_decode($this->base64Contents, true);

    return false === $contents ? '' : $contents;
  }
}
