<?php

declare(strict_types=1);

namespace Equipment\Infrastructure\Adapter\Inspection;

use Doctrine\ORM\EntityManagerInterface;
use Equipment\Infrastructure\Persistence\Doctrine\Record\EquipmentRecord;
use Inspection\Application\Port\Outbound\EquipmentNamingPort;

/**
 * Adapter EquipmentNamingAdapter.
 *
 * Equipment-side implementation of the Inspection module's naming contract:
 * resolves equipment identifiers into serial numbers in a single query.
 *
 * @category Outbound Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class EquipmentNamingAdapter implements EquipmentNamingPort
{
  // #region Constructor
  /**
   * Constructor.
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
   * Method findSerialNumbersByIds.
   * {@inheritdoc}
   *
   * @since 1.0.0
   *
   * @param list<string> $equipmentIds the equipment identifiers to resolve
   *
   * @return array<string, string> serial number keyed by equipment identifier
   */
  public function findSerialNumbersByIds(array $equipmentIds): array
  {
    if ([] === $equipmentIds) {
      return [];
    }

    /** @var list<array{id: string, serialNumber: ?string}> $rows */
    $rows = $this->entityManager->createQueryBuilder()
      ->select('equipment.id AS id', 'equipment.serialNumber AS serialNumber')
      ->from(EquipmentRecord::class, 'equipment')
      ->where('equipment.id IN (:equipmentIds)')
      ->setParameter('equipmentIds', $equipmentIds)
      ->getQuery()
      ->getArrayResult();

    $serials = [];
    foreach ($rows as $row) {
      if (null !== $row['serialNumber'] && '' !== $row['serialNumber']) {
        $serials[$row['id']] = $row['serialNumber'];
      }
    }

    return $serials;
  }
  // #endregion
}
