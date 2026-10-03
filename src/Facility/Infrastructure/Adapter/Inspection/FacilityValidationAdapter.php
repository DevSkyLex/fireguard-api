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
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FacilityValidationAdapter implements FacilityValidationPort
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Receives the facility repository used to validate facility references for Inspection.
   *
   * @access public
   *
   * @param FacilityRepositoryPort $facilityRepository port used to find and validate facility references
   * @param ?\Facility\Application\Port\Inbound\FacilityLifecycleReferencePort $lifecycle the shared publication scope policy
   * @param ?\Doctrine\ORM\EntityManagerInterface $entityManager the main relation transaction
   * @param ?\Facility\Application\Port\Inbound\FacilityHierarchyPort $hierarchy the organization relation lock
   *
   * @return void
   */
  public function __construct(
    private FacilityRepositoryPort $facilityRepository,
    private ?\Facility\Application\Port\Inbound\FacilityLifecycleReferencePort $lifecycle = null,
    private ?\Doctrine\ORM\EntityManagerInterface $entityManager = null,
    private ?\Facility\Application\Port\Inbound\FacilityHierarchyPort $hierarchy = null,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * {@inheritDoc}
   */
  public function assertFacilityIsUsable(string $facilityId, string $organizationId, ?string $interventionId = null): void
  {
    if ($this->entityManager?->getConnection()->isTransactionActive()) {
      $this->hierarchy?->lock($organizationId);
    }
    $facility = $this->facilityRepository->findById(FacilityId::fromString($facilityId));

    if (null === $facility || (string) $facility->organizationId() !== $organizationId) {
      throw new InvalidArgumentException(sprintf('Facility with ID "%s" not found.', $facilityId));
    }

    if (!$facility->status()->isActive()) {
      throw new InvalidArgumentException(sprintf('Facility with ID "%s" is archived and cannot be used.', $facilityId));
    }
    $this->lifecycle?->assertReference($organizationId, $facilityId, $interventionId);
  }
  // #endregion
}
