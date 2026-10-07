<?php

declare(strict_types=1);

namespace MaintenanceExport\Application\UseCase\Query\ReadMaintenanceExport;

use Shared\Application\Message\QueryMessage;

/**
 * Class ReadMaintenanceExportQuery
 * Authorized metadata, source directory and saved-byte reads.
 *
 * @category Query
 */
final readonly class ReadMaintenanceExportQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @return void
   */
  public function __construct(public string $actorId, public string $organizationId, public string $action, public ?string $id = null, public ?string $format = null, public ?string $system = null, public ?string $resourceType = null, public ?string $resourceId = null, public int $page = 1, public int $itemsPerPage = 30, public ?string $search = null)
  {
  }
  // #endregion
}
