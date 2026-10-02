<?php

declare(strict_types=1);

namespace Equipment\Infrastructure\Adapter\Maintenance;

use Doctrine\ORM\EntityManagerInterface;
use Equipment\Infrastructure\Exception\EquipmentOrganizationMissingException;
use Equipment\Infrastructure\Persistence\Doctrine\Record\EquipmentRecord;
use Maintenance\Application\Contract\Directory\TrackableEquipment;
use Maintenance\Application\Port\Outbound\Directory\MaintenanceEquipmentDirectoryPort;

use function array_map;
use function max;

/**
 * Adapter EquipmentMaintenanceDirectoryAdapter.
 *
 * Implements the Maintenance module's equipment directory port, querying
 * `EquipmentRecord` directly (mirrors `EquipmentStatisticsAdapter`'s entity
 * manager wiring): the cross-organization paginated listing the sweep needs
 * has no equivalent in `EquipmentRepositoryPort`, so — like
 * `Intervention\Infrastructure\Adapter\Organization\InterventionStatisticsAdapter`
 * queries `InterventionRecord` directly — this adapter reads the record
 * directly rather than growing the domain-facing port for a cross-module
 * read model.
 *
 * Only PUBLISHED equipment is considered: draft intervention scratchpads
 * are invisible here, the same visibility rule `findPublishedById` applies.
 *
 * @category Adapter
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class EquipmentMaintenanceDirectoryAdapter implements MaintenanceEquipmentDirectoryPort
{
  // #region Constants
  /**
   * Constant TRACKABLE_EQUIPMENT_PROJECTION
   *
   * Keeps the scalar directory view identical across single, batch and paginated reads.
   */
  private const string TRACKABLE_EQUIPMENT_PROJECTION = 'e.id AS equipmentId, IDENTITY(e.organization) AS organizationId, e.facilityId AS facilityId, e.type AS equipmentType, e.status AS status';
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager the entity manager value
   */
  public function __construct(
    private EntityManagerInterface $entityManager,
  ) {
  }

  // #endregion
  // #region Methods
  /**
   * Method findEquipment
   *
   * Looks up published equipment by identifier and returns its trackable view; missing equipment produces null.
   *
   * @access public
   *
   * @param string $equipmentId the equipment identifier
   *
   * @return ?TrackableEquipment
   */
  public function findEquipment(string $equipmentId): ?TrackableEquipment
  {
    /** @var array{equipmentId: string, organizationId: string, facilityId: ?string, equipmentType: string, status: string}|null $row */
    $row = $this->entityManager->createQueryBuilder()
      ->select(self::TRACKABLE_EQUIPMENT_PROJECTION)
      ->from(EquipmentRecord::class, 'e')->where('e.id = :id AND e.recordStatus = :status')
      ->setParameter('id', $equipmentId)->setParameter('status', 'published')->getQuery()->getOneOrNullResult(\Doctrine\ORM\Query::HYDRATE_ARRAY);

    return null === $row ? null : $this->scalarView($row);
  }

  /**
   * @param list<string> $equipmentIds
   *
   * @return list<TrackableEquipment>
   */
  public function findEquipmentByIds(array $equipmentIds): array
  {
    if ([] === $equipmentIds) {
      return [];
    }
    /** @var list<array{equipmentId: string, organizationId: string, facilityId: ?string, equipmentType: string, status: string}> $rows */
    $rows = $this->entityManager->createQueryBuilder()
      ->select(self::TRACKABLE_EQUIPMENT_PROJECTION)
      ->from(EquipmentRecord::class, 'e')->where('e.id IN (:ids) AND e.recordStatus = :status')
      ->setParameter('ids', $equipmentIds)->setParameter('status', 'published')->getQuery()->getArrayResult();

    return array_map($this->scalarView(...), $rows);
  }

  /**
   * Method listEquipmentPage
   *
   * Lists published equipment in a stable, bounded page and optionally scopes it to an organization. Decommissioned equipment remains included so maintenance schedules can be reconciled.
   *
   * @access public
   *
   * @param int $limit the maximum number of results
   * @param int $offset the result offset
   * @param ?string $organizationId the optional organization identifier
   *
   * @return list<TrackableEquipment> the equipment page
   */
  public function listEquipmentPage(int $limit, int $offset, ?string $organizationId = null): array
  {
    $qb = $this->entityManager->createQueryBuilder()
      ->select(self::TRACKABLE_EQUIPMENT_PROJECTION)
      ->from(EquipmentRecord::class, 'e')
      ->where('e.recordStatus = :recordStatus')
      ->setParameter('recordStatus', 'published')
      ->orderBy('e.id', 'ASC')
      ->setFirstResult(max(0, $offset))
      ->setMaxResults(max(1, $limit));

    if (null !== $organizationId) {
      $qb->andWhere('IDENTITY(e.organization) = :organization')->setParameter('organization', $organizationId);
    }

    /** @var list<array{equipmentId: string, organizationId: string, facilityId: ?string, equipmentType: string, status: string}> $rows */
    $rows = $qb->getQuery()->getArrayResult();

    return array_map($this->scalarView(...), $rows);
  }

  /**
   * @param array{equipmentId: string, organizationId: ?string, facilityId: ?string, equipmentType: string, status: string} $row
   */
  private function scalarView(array $row): TrackableEquipment
  {
    if (null === $row['organizationId']) {
      throw new EquipmentOrganizationMissingException('Equipment organization is missing.');
    }

    return new TrackableEquipment(...$row);
  }
  // #endregion
}
