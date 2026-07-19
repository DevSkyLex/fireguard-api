<?php

declare(strict_types=1);

namespace Equipment\Infrastructure\Adapter\Facility;

use Doctrine\ORM\EntityManagerInterface;
use Equipment\Infrastructure\Persistence\Doctrine\Record\EquipmentRecord;
use Facility\Application\Port\Outbound\FacilityEquipmentDependencyPort;

/**
 * Adapter FacilityEquipmentDependencyAdapter.
 *
 * Equipment-side implementation of the Facility archival dependency contract:
 * reports whether a facility still has active (non-decommissioned, published)
 * equipment assigned to it.
 *
 * @category Outbound Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FacilityEquipmentDependencyAdapter implements FacilityEquipmentDependencyPort
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the FacilityEquipmentDependencyAdapter class.
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
   * Method hasActiveEquipmentInFacility.
   * {@inheritdoc}
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $facilityId the facility identifier
   *
   * @return bool whether active equipment is assigned to the facility
   */
  public function hasActiveEquipmentInFacility(string $organizationId, string $facilityId): bool
  {
    $count = (int) $this->entityManager->createQueryBuilder()
      ->select('COUNT(record.id)')
      ->from(EquipmentRecord::class, 'record')
      ->where('record.facilityId = :facilityId')
      ->andWhere('IDENTITY(record.organization) = :organizationId')
      ->andWhere('record.recordStatus = :published')
      ->andWhere('record.status != :decommissioned')
      ->setParameter('facilityId', $facilityId)
      ->setParameter('organizationId', $organizationId)
      ->setParameter('published', 'published')
      ->setParameter('decommissioned', 'decommissioned')
      ->getQuery()
      ->getSingleScalarResult();

    return $count > 0;
  }
  // #endregion
}
