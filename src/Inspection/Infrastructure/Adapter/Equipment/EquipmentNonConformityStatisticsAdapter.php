<?php

declare(strict_types=1);

namespace Inspection\Infrastructure\Adapter\Equipment;

use Equipment\Application\Port\Outbound\NonConformityStatisticsPort;
use Inspection\Application\Port\Outbound\NonConformityRepositoryPort;
use Inspection\Domain\ValueObject\{InspectionOrganizationId, NonConformityStatus};

/**
 * Adapter EquipmentNonConformityStatisticsAdapter.
 *
 * Implements the Equipment module's cross-module open-non-conformity-count
 * port using the Inspection module's own `NonConformityRepositoryPort` —
 * composing two already-tested, single-purpose count queries (`open` and
 * `in_progress`) rather than introducing new DQL. See the port's docblock
 * for why this is an ORGANIZATION-WIDE counter, not a per-equipment one.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class EquipmentNonConformityStatisticsAdapter implements NonConformityStatisticsPort
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param NonConformityRepositoryPort $nonConformityRepository the non-conformity repository port
   */
  public function __construct(
    private NonConformityRepositoryPort $nonConformityRepository,
  ) {
  }
  // #endregion

  // #region Methods
  public function countOpenNonConformities(string $organizationId): int
  {
    $organization = InspectionOrganizationId::fromString($organizationId);

    return $this->nonConformityRepository->countByOrganizationId($organization, status: NonConformityStatus::OPEN->value)
      + $this->nonConformityRepository->countByOrganizationId($organization, status: NonConformityStatus::IN_PROGRESS->value);
  }
  // #endregion
}
