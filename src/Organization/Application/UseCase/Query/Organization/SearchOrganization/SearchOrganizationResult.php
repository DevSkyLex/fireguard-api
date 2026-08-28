<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Organization\SearchOrganization;

use Organization\Application\Contract\Search\OrganizationSearchHit;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase SearchOrganizationResult.
 *
 * The global-search snapshot, grouped by result type. A type the caller may
 * not read is an empty list here — indistinguishable, on purpose, from a
 * type with no match.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class SearchOrganizationResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param list<OrganizationSearchHit> $equipments the equipment hits
   * @param list<OrganizationSearchHit> $facilities the facility hits
   * @param list<OrganizationSearchHit> $interventions the intervention hits
   * @param list<OrganizationSearchHit> $inspections the inspection hits
   * @param list<OrganizationSearchHit> $nonConformities the non-conformity hits
   */
  public function __construct(
    public array $equipments,
    public array $facilities,
    public array $interventions,
    public array $inspections,
    public array $nonConformities,
  ) {
  }
  // #endregion
}
