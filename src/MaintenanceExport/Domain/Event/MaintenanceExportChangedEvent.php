<?php

declare(strict_types=1);

namespace MaintenanceExport\Domain\Event;

use DateTimeImmutable;

/**
 * Class MaintenanceExportChangedEvent
 * Durable identifiers omit ERP bytes and internal financial data.
 *
 * @category Event
 */
final readonly class MaintenanceExportChangedEvent
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @return void
   */
  public function __construct(public string $organizationId, public string $resourceId, public string $change, public DateTimeImmutable $occurredAt)
  {
  }
  // #endregion
}
