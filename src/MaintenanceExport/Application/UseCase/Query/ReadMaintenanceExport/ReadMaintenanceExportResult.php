<?php

declare(strict_types=1);

namespace MaintenanceExport\Application\UseCase\Query\ReadMaintenanceExport;

use Shared\Application\Message\ResultMessage;

/**
 * Class ReadMaintenanceExportResult
 * Transport-independent page or exact saved artifact.
 *
 * @category Result
 */
final readonly class ReadMaintenanceExportResult implements ResultMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @param list<array<string,mixed>> $items authorized metadata
   *
   * @return void
   */
  public function __construct(public string $kind, public array $items, public int $total = 0, public int $page = 1, public int $itemsPerPage = 30, public bool $collection = false, public ?string $bytes = null, public ?string $mediaType = null, public ?string $fileName = null, public ?string $sha256 = null)
  {
  }
  // #endregion
}
