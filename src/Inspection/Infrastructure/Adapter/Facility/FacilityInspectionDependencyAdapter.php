<?php

declare(strict_types=1);

namespace Inspection\Infrastructure\Adapter\Facility;

use Doctrine\ORM\EntityManagerInterface;
use Facility\Application\Port\Outbound\FacilityInspectionDependencyPort;
use Inspection\Infrastructure\Persistence\Doctrine\Record\InspectionRecord;

/**
 * Adapter FacilityInspectionDependencyAdapter.
 *
 * Inspection-side implementation of the Facility archival dependency contract:
 * reports whether a facility still has an in-progress (draft or submitted,
 * published) inspection targeting it.
 *
 * @category Outbound Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FacilityInspectionDependencyAdapter implements FacilityInspectionDependencyPort
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the FacilityInspectionDependencyAdapter class.
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
   * Method hasActiveInspectionInFacility.
   * {@inheritdoc}
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $facilityId the facility identifier
   *
   * @return bool whether an in-progress inspection targets the facility
   */
  public function hasActiveInspectionInFacility(string $organizationId, string $facilityId): bool
  {
    $count = (int) $this->entityManager->createQueryBuilder()
      ->select('COUNT(record.id)')
      ->from(InspectionRecord::class, 'record')
      ->where('record.facilityId = :facilityId')
      ->andWhere('IDENTITY(record.organization) = :organizationId')
      ->andWhere('record.recordStatus = :published')
      ->andWhere('record.status IN (:activeStatuses)')
      ->setParameter('facilityId', $facilityId)
      ->setParameter('organizationId', $organizationId)
      ->setParameter('published', 'published')
      ->setParameter('activeStatuses', ['draft', 'submitted'])
      ->getQuery()
      ->getSingleScalarResult();

    return $count > 0;
  }
  // #endregion
}
