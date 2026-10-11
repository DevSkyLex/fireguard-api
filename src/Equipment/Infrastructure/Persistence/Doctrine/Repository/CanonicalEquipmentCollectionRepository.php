<?php

declare(strict_types=1);

namespace Equipment\Infrastructure\Persistence\Doctrine\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Equipment\Application\Contract\Equipment\{CanonicalEquipmentCollectionItem, CanonicalEquipmentCollectionPage, CanonicalEquipmentSnapshot};
use Equipment\Application\Port\Outbound\CanonicalEquipmentCollectionPort;
use Equipment\Infrastructure\Persistence\Doctrine\Record\EquipmentRecord;

use function array_map;

/** Reads canonical pages using the same predicates before count and pagination. @category Repository @version 1.0.0 @author Valentin FORTIN <contact@valentin-fortin.pro> */
final readonly class CanonicalEquipmentCollectionRepository implements CanonicalEquipmentCollectionPort
{
  // #region Constructor
  /**
   * @since 1.0.0
   */
  public function __construct(private EntityManagerInterface $entityManager)
  {
  }
  // #endregion

  // #region Methods
  /**
   * {@inheritdoc} @since 1.0.0
   */
  public function read(string $organizationId, string $recordStatus, ?string $interventionId, ?string $facilityId, int $page, int $itemsPerPage): CanonicalEquipmentCollectionPage
  {
    $query = $this->entityManager->createQueryBuilder()->select('e')->from(EquipmentRecord::class, 'e')
      ->where('e.organizationId = :organization')->andWhere('e.recordStatus = :recordStatus')
      ->setParameter('organization', $organizationId)->setParameter('recordStatus', $recordStatus)->orderBy('e.createdAt', 'ASC');
    if (null !== $interventionId) {
      $query->andWhere('e.interventionId = :intervention')->setParameter('intervention', $interventionId);
    }
    if (null !== $facilityId) {
      $query->andWhere('e.facilityId = :facility')->setParameter('facility', $facilityId);
    }
    $count = clone $query;
    $total = (int) $count->resetDQLPart('orderBy')->select('COUNT(e.id)')->getQuery()->getSingleScalarResult();
    /** @var list<EquipmentRecord> $records */
    $records = $query->setFirstResult(($page - 1) * $itemsPerPage)->setMaxResults($itemsPerPage)->getQuery()->getResult();

    return new CanonicalEquipmentCollectionPage(array_map(fn (EquipmentRecord $record): CanonicalEquipmentCollectionItem => new CanonicalEquipmentCollectionItem($this->snapshot($record)), $records), $total, $page, $itemsPerPage);
  }

  /**
   * @since 1.0.0
   */
  private function snapshot(EquipmentRecord $record): CanonicalEquipmentSnapshot
  {
    return new CanonicalEquipmentSnapshot(
      id: $record->id,
      organizationId: $record->organizationId,
      interventionId: $record->interventionId,
      recordStatus: $record->recordStatus,
      revision: $record->revision,
      facilityId: $record->facilityId,
      type: $record->type,
      subType: $record->subType,
      brand: $record->brand,
      model: $record->model,
      serialNumber: $record->serialNumber,
      locationLabel: $record->locationLabel,
      name: $record->name,
      assetCode: $record->assetCode,
      criticality: $record->criticality,
      technicalProperties: $record->technicalProperties,
      predecessorEquipmentId: $record->predecessorEquipmentId,
      successorEquipmentId: $record->successorEquipmentId,
      status: $record->status,
      installedAt: $record->installedAt?->format('c'),
      commissionedAt: $record->commissionedAt?->format('c'),
      createdAt: $record->createdAt->format('c'),
      updatedAt: $record->updatedAt->format('c'),
    );
  }
  // #endregion
}
