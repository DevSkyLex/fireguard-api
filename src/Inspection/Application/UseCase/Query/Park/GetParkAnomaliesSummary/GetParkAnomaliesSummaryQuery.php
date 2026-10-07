<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Query\Park\GetParkAnomaliesSummary;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase GetParkAnomaliesSummaryQuery.
 *
 * Counts the same unresolved scope as the Parc anomaly collection.
 *
 * @category UseCase
 */
final readonly class GetParkAnomaliesSummaryQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * @access public
   *
   * @param string $organizationId owning organization
   * @param string|null $family fire, safety or other; null selects all
   * @param string|null $customerId optional internal customer
   * @param string|null $facilityId optional published facility
   * @param bool $includeDescendants whether the facility includes its published descendants
   *
   * @return void
   */
  public function __construct(
    public string $organizationId,
    public ?string $family = null,
    public ?string $customerId = null,
    public ?string $facilityId = null,
    public bool $includeDescendants = true,
  ) {
  }
  // #endregion
}
