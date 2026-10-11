<?php

declare(strict_types=1);

namespace Equipment\Infrastructure\Persistence\Doctrine\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Equipment\Application\Port\Outbound\EquipmentMediaLockPort;
use Equipment\Infrastructure\Persistence\Doctrine\Record\EquipmentRecord;

/** Locks and refreshes the mutable owner before a media mutation. @category Repository @version 1.0.0 @author Valentin FORTIN <contact@valentin-fortin.pro> */
final readonly class EquipmentMediaLockRepository implements EquipmentMediaLockPort
{
  /**
   * @since 1.0.0
   */
  public function __construct(private EntityManagerInterface $entityManager)
  {
  }

  /**
   * {@inheritdoc} @since 1.0.0
   */
  public function lock(string $equipmentId): void
  {
    $this->entityManager->getConnection()->executeStatement('SELECT id FROM equipment WHERE id = :id FOR UPDATE', ['id' => $equipmentId]);
    $record = $this->entityManager->find(EquipmentRecord::class, $equipmentId);
    if ($record instanceof EquipmentRecord) {
      $this->entityManager->refresh($record);
    }
  }
}
