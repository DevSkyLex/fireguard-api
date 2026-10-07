<?php

declare(strict_types=1);

namespace Procurement\Application\Port\Inbound;

use DateTimeImmutable;
use Procurement\Application\Contract\Reporting\{ProcurementEconomicOverview, ProcurementEconomicOverviewUnavailable};

/**
 * Interface ProcurementEconomicOverviewPort
 *
 * Publishes private purchase economics to a caller that already verified organization scope and financial read permission.
 *
 * @category Port
 */
interface ProcurementEconomicOverviewPort
{
  // #region Methods
  /**
   * Method overview
   *
   * Reads one consistent main-database statement. Selects at most 500 non-draft orders by creation time;
   * physical deliveries and returns on those orders cover their entire retained history.
   *
   * @access public
   *
   * @param string $organizationId trusted owning organization identifier
   * @param DateTimeImmutable $from inclusive first order-creation instant
   * @param DateTimeImmutable $to exclusive final order-creation instant
   *
   * @return ProcurementEconomicOverview exact amounts without supplier contacts or stock duplication
   *
   * @throws ProcurementEconomicOverviewUnavailable when dates, cardinality or currencies prevent a reliable report
   */
  public function overview(string $organizationId, DateTimeImmutable $from, DateTimeImmutable $to): ProcurementEconomicOverview;
  // #endregion
}
