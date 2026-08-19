<?php

declare(strict_types=1);

namespace Intervention\Infrastructure\Adapter\Facility;

use Doctrine\ORM\EntityManagerInterface;
use Facility\Application\Port\Outbound\FacilityInterventionDependencyPort;
use Intervention\Domain\ValueObject\InterventionStatus;
use Intervention\Infrastructure\Persistence\Doctrine\Record\InterventionRecord;

/**
 * Adapter FacilityInterventionDependencyAdapter.
 *
 * Intervention-side implementation of the Facility archival dependency contract:
 * reports whether a facility still has an active (not closed) intervention
 * targeting it as its site.
 *
 * @category Outbound Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FacilityInterventionDependencyAdapter implements FacilityInterventionDependencyPort
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the FacilityInterventionDependencyAdapter class.
   *
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager the entity manager
   */
  public function __construct(
    private EntityManagerInterface $entityManager,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method hasActiveInterventionInFacility.
   * {@inheritdoc}
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $facilityId the facility identifier
   *
   * @return bool whether an active intervention targets the facility
   */
  public function hasActiveInterventionInFacility(string $organizationId, string $facilityId): bool
  {
    $count = (int) $this->entityManager->createQueryBuilder()
      ->select('COUNT(record.id)')
      ->from(InterventionRecord::class, 'record')
      ->where('record.siteId = :facilityId')
      ->andWhere('IDENTITY(record.organization) = :organizationId')
      ->andWhere('record.status NOT IN (:closedStatuses)')
      ->setParameter('facilityId', $facilityId)
      ->setParameter('organizationId', $organizationId)
      ->setParameter('closedStatuses', InterventionStatus::closedValues())
      ->getQuery()
      ->getSingleScalarResult();

    return $count > 0;
  }
  // #endregion
}
