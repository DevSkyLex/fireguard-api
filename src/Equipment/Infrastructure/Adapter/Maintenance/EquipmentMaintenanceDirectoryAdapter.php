<?php

declare(strict_types=1);

namespace Equipment\Infrastructure\Adapter\Maintenance;

use Doctrine\ORM\EntityManagerInterface;
use Equipment\Infrastructure\Persistence\Doctrine\Record\EquipmentRecord;
use Maintenance\Application\Contract\Directory\TrackableEquipment;
use Maintenance\Application\Port\Outbound\Directory\MaintenanceEquipmentDirectoryPort;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use RuntimeException;

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
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class EquipmentMaintenanceDirectoryAdapter implements MaintenanceEquipmentDirectoryPort
{
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
  public function findEquipment(string $equipmentId): ?TrackableEquipment
  {
    /** @var ?EquipmentRecord $record */
    $record = $this->entityManager->getRepository(EquipmentRecord::class)->findOneBy([
      'id' => $equipmentId,
      'recordStatus' => 'published',
    ]);

    return $record instanceof EquipmentRecord ? $this->view($record) : null;
  }

  public function listEquipmentPage(int $limit, int $offset): array
  {
    $qb = $this->entityManager->createQueryBuilder()
      ->select('e')
      ->from(EquipmentRecord::class, 'e')
      ->where('e.recordStatus = :recordStatus')
      ->setParameter('recordStatus', 'published')
      ->orderBy('e.id', 'ASC')
      ->setFirstResult(max(0, $offset))
      ->setMaxResults(max(1, $limit));

    /** @var list<EquipmentRecord> $records */
    $records = $qb->getQuery()->getResult();

    return array_map($this->view(...), $records);
  }

  /**
   * Method view.
   *
   * @since 1.0.0
   *
   * @param EquipmentRecord $record the record value
   *
   * @return TrackableEquipment the trackable equipment view
   */
  private function view(EquipmentRecord $record): TrackableEquipment
  {
    if (!$record->organization instanceof OrganizationRecord) {
      throw new RuntimeException('Equipment organization is missing.');
    }

    return new TrackableEquipment(
      equipmentId: $record->id,
      organizationId: $record->organization->id,
      facilityId: $record->facilityId,
      equipmentType: $record->type,
      status: $record->status,
    );
  }
  // #endregion
}
