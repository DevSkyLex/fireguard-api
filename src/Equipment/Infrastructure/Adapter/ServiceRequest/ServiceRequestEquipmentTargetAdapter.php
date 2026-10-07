<?php

declare(strict_types=1);

namespace Equipment\Infrastructure\Adapter\ServiceRequest;

use Doctrine\DBAL\LockMode;
use Doctrine\ORM\{EntityManagerInterface,Query};
use Equipment\Infrastructure\Persistence\Doctrine\Record\EquipmentRecord;
use ServiceRequest\Application\Contract\Target\ServiceRequestEquipmentTarget;
use ServiceRequest\Application\Port\Outbound\ServiceRequestEquipmentTargetPort;

/** Owner-scoped published equipment; active main transactions serialize lifecycle conversion. @category Adapter */
final readonly class ServiceRequestEquipmentTargetAdapter implements ServiceRequestEquipmentTargetPort
{
  public function __construct(private EntityManagerInterface $entityManager)
  {
  }

  public function find(string $equipmentId, string $organizationId): ?ServiceRequestEquipmentTarget
  {
    $query = $this->entityManager->createQueryBuilder()->select('equipment')->from(EquipmentRecord::class, 'equipment')->where('equipment.id=:id')->andWhere('IDENTITY(equipment.organization)=:organization')->andWhere("equipment.recordStatus='published'")->setParameter('id', $equipmentId)->setParameter('organization', $organizationId)->getQuery()->setHint(Query::HINT_REFRESH, true);
    if ($this->entityManager->getConnection()->isTransactionActive()) {
      $query->setLockMode(LockMode::PESSIMISTIC_WRITE);
    }
    $record = $query->getOneOrNullResult();

    return $record instanceof EquipmentRecord ? new ServiceRequestEquipmentTarget($record->id, $record->name, $record->assetCode, $record->status, $record->facilityId) : null;
  }
}
