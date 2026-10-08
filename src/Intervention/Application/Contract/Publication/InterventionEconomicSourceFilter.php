<?php

declare(strict_types=1);

namespace Intervention\Application\Contract\Publication;

use DateTimeImmutable;

/**
 * Class InterventionEconomicSourceFilter
 *
 * Groups operational source predicates while the port keeps organization scope and pagination explicit.
 * Financial identifiers are authorized by the consuming use case; no financial values cross this contract.
 *
 * @category Contract
 */
final readonly class InterventionEconomicSourceFilter
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Retains caller criteria without changing the source reader's bounded validation order.
   *
   * @access public
   *
   * @param ?string $search literal title or sequence search
   * @param ?DateTimeImmutable $from inclusive source date
   * @param ?DateTimeImmutable $to exclusive source date
   * @param ?string $siteId optional root site
   * @param ?string $customerId optional internal client
   * @param ?string $equipmentId optional asset
   * @param list<string> $financialInterventionIds at most 10000 finance-authorized additional matching sources
   *
   * @return void
   */
  public function __construct(
    public ?string $search = null,
    public ?DateTimeImmutable $from = null,
    public ?DateTimeImmutable $to = null,
    public ?string $siteId = null,
    public ?string $customerId = null,
    public ?string $equipmentId = null,
    public array $financialInterventionIds = [],
  ) {
  }
  // #endregion
}
