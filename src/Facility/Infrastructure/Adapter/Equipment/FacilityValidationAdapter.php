<?php

declare(strict_types=1);

namespace Facility\Infrastructure\Adapter\Equipment;

use Equipment\Application\Port\Outbound\FacilityValidationPort;
use Facility\Application\Contract\Facility\FacilityListCriteria;
use Facility\Application\Port\Outbound\FacilityRepositoryPort;
use Facility\Domain\ValueObject\{FacilityId, FacilityOrganizationId};
use InvalidArgumentException;

use function in_array;
use function sprintf;

/**
 * Adapter FacilityValidationAdapter.
 *
 * Implements the Equipment module's facility validation port
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
   * Receives the facility repository used to validate facility references for Equipment.
   *
   * @access public
   *
   * @param FacilityRepositoryPort $facilityRepository port used to find and validate facility references
   *
   * @return void
   */
  public function __construct(
    private FacilityRepositoryPort $facilityRepository,
    private ?\Doctrine\ORM\EntityManagerInterface $entityManager = null,
    private ?\Facility\Application\Service\FacilityHierarchyPublicationContext $publicationContext = null,
    private ?\Facility\Application\Port\Inbound\FacilityLifecycleReferencePort $lifecycle = null,
    private ?\Facility\Application\Port\Inbound\FacilityHierarchyPort $hierarchy = null,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * {@inheritDoc}
   */
  public function assertFacilityIsAssignable(string $facilityId, string $organizationId, ?string $interventionId = null, ?string $publishingInterventionId = null): void
  {
    if ($this->entityManager?->getConnection()->isTransactionActive()) {
      $this->hierarchy?->lock($organizationId);
    }
    $facility = $this->facilityRepository->findById(FacilityId::fromString($facilityId));

    if (null === $facility || (string) $facility->organizationId() !== $organizationId) {
      throw new InvalidArgumentException(sprintf('Facility with ID "%s" not found.', $facilityId));
    }

    $merged = $this->publicationContext?->node($organizationId, $facilityId);
    if (null === $merged ? !$facility->status()->isActive() : 'active' !== $merged->status) {
      throw new InvalidArgumentException(sprintf('Facility with ID "%s" is archived and cannot be used.', $facilityId));
    }
    if (null !== $this->entityManager) {
      $record = $this->entityManager->find(\Facility\Infrastructure\Persistence\Doctrine\Record\FacilityRecord::class, $facilityId);
      if (null === $merged && (!$record instanceof \Facility\Infrastructure\Persistence\Doctrine\Record\FacilityRecord
        || ('published' !== $record->recordStatus
          && ('draft' !== $record->recordStatus || null === $record->interventionId
            || !in_array($record->interventionId, [$interventionId, $publishingInterventionId], true))))) {
        throw new InvalidArgumentException('The facility publication state is incompatible with this resource.');
      }
    }
    $this->lifecycle?->assertReference($organizationId, $facilityId, $interventionId, $publishingInterventionId);
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
      criteria: new FacilityListCriteria(code: $code),
      limit: 1,
      offset: 0,
    );

    return isset($matches[0]) ? $matches[0]->id()->__toString() : null;
  }
  // #endregion
}
