<?php

declare(strict_types=1);

namespace Facility\Infrastructure\Adapter\Inspection;

use Facility\Application\Port\Outbound\FacilityRepositoryPort;
use Facility\Domain\ValueObject\FacilityId;
use Inspection\Application\Port\Outbound\FacilityValidationPort;
use InvalidArgumentException;

use function sprintf;

/**
 * Adapter FacilityValidationAdapter.
 *
 * Implements the Inspection module's facility validation port
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
  public function assertFacilityIsUsable(string $facilityId, string $organizationId): void
  {
    $facility = $this->facilityRepository->findById(FacilityId::fromString($facilityId));

    if (null === $facility || (string) $facility->organizationId() !== $organizationId) {
      throw new InvalidArgumentException(sprintf('Facility with ID "%s" not found.', $facilityId));
    }

    if (!$facility->status()->isActive()) {
      throw new InvalidArgumentException(sprintf('Facility with ID "%s" is archived and cannot be used.', $facilityId));
    }
  }
  // #endregion
}
