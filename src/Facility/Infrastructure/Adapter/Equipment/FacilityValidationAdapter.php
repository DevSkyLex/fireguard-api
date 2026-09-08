<?php

declare(strict_types=1);

namespace Facility\Infrastructure\Adapter\Equipment;

use Equipment\Application\Port\Outbound\FacilityValidationPort;
use Facility\Application\Port\Outbound\FacilityRepositoryPort;
use Facility\Domain\ValueObject\{FacilityId, FacilityOrganizationId};
use InvalidArgumentException;

use function sprintf;

/**
 * Adapter FacilityValidationAdapter.
 *
 * Implements the Equipment module's facility validation port
 * using the Facility module's repository.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FacilityValidationAdapter implements FacilityValidationPort
{
  // #region Constructor
  public function __construct(
    private FacilityRepositoryPort $facilityRepository,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * {@inheritDoc}
   */
  public function assertFacilityIsAssignable(string $facilityId, string $organizationId): void
  {
    $facility = $this->facilityRepository->findById(FacilityId::fromString($facilityId));

    if (null === $facility || (string) $facility->organizationId() !== $organizationId) {
      throw new InvalidArgumentException(sprintf('Facility with ID "%s" not found.', $facilityId));
    }

    if (!$facility->status()->isActive()) {
      throw new InvalidArgumentException(sprintf('Facility with ID "%s" is archived and cannot be used.', $facilityId));
    }
  }

  /**
   * Method belongsToOrganization.
   *
   * @since 1.1.0
   *
   * @param string $facilityId the facility identifier
   * @param string $organizationId the expected organization identifier
   *
   * @return bool true when the facility exists and belongs to that organization
   */
  public function belongsToOrganization(string $facilityId, string $organizationId): bool
  {
    $facility = $this->facilityRepository->findById(FacilityId::fromString($facilityId));

    return null !== $facility && (string) $facility->organizationId() === $organizationId;
  }

  /**
   * Method resolveIdByCode.
   *
   * @since 1.2.0
   *
   * @param string $organizationId the owning organization identifier
   * @param string $code the facility code to resolve
   *
   * @return ?string the facility identifier, or null when no active facility carries that code
   */
  public function resolveIdByCode(string $organizationId, string $code): ?string
  {
    $matches = $this->facilityRepository->findByOrganizationId(
      organizationId: FacilityOrganizationId::fromString($organizationId),
      includeArchived: false,
      code: $code,
      limit: 1,
      offset: 0,
    );

    return isset($matches[0]) ? $matches[0]->id()->__toString() : null;
  }
  // #endregion
}
