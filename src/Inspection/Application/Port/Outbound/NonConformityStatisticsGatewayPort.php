<?php

declare(strict_types=1);

namespace Inspection\Application\Port\Outbound;

use DateTimeImmutable;
use Inspection\Application\Contract\Statistics\NonConformityStatisticsAggregate;

/**
 * Port NonConformityStatisticsGatewayPort.
 *
 * Computes the organization-wide non-conformity statistics snapshot in a
 * bounded number of grouped queries — never one query per severity, per
 * facility or per equipment type.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface NonConformityStatisticsGatewayPort
{
  // #region Methods
  /**
   * Method aggregate.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param ?DateTimeImmutable $from inclusive `createdAt` lower bound, or null
   * @param ?DateTimeImmutable $to inclusive `createdAt` upper bound, or null
   *
   * @return NonConformityStatisticsAggregate the raw snapshot
   */
  public function aggregate(string $organizationId, ?DateTimeImmutable $from, ?DateTimeImmutable $to): NonConformityStatisticsAggregate;
  // #endregion
}
