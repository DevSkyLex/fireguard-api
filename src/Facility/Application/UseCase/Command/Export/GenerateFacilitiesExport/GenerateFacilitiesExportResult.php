<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Command\Export\GenerateFacilitiesExport;

use Shared\Application\Message\ResultMessage;

/** Saves exact CSV bytes and original download metadata with their owning export audit. @category UseCase */
final readonly class GenerateFacilitiesExportResult implements ResultMessage
{
  /**
   * @param array<string, string> $headers original scalar artifact headers
   */
  public function __construct(public string $contentsBase64, public string $fileName, public string $contentType = 'text/csv; charset=utf-8', public array $headers = ['X-Accel-Buffering' => 'no'])
  {
  }
}
