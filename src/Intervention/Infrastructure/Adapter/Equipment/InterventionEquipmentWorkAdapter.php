<?php

declare(strict_types=1);

namespace Intervention\Infrastructure\Adapter\Equipment;

use Doctrine\ORM\EntityManagerInterface;
use Intervention\Application\Contract\Resource\InterventionEquipmentWork;
use Intervention\Application\Port\Outbound\InterventionEquipmentWorkPort;
use Intervention\Infrastructure\Persistence\Doctrine\Record\InterventionWorkItemRecord;

use function is_array;
use function json_decode;

/**
 * Class InterventionEquipmentWorkAdapter
 *
 * Reads both deployed JSON targets and canonical equipment IRIs without cross-module persistence.
 *
 * @category Adapter
 */
final readonly class InterventionEquipmentWorkAdapter implements InterventionEquipmentWorkPort
{
  /**
   * Method __construct
   *
   * @access public
   *
   * @param EntityManagerInterface $entityManager owning main manager
   *
   * @return void
   */
  public function __construct(private EntityManagerInterface $entityManager)
  {
  }

  /**
   * Method findOpenWork
   *
   * Keeps completed-but-unpublished work visible; published, abandoned and skipped work is excluded.
   *
   * @access public
   *
   * @param string $organizationId authorized organization
   * @param string $equipmentId equipment identity
   *
   * @return list<InterventionEquipmentWork> existing work
   */
  public function findOpenWork(string $organizationId, string $equipmentId): array
  {
    /** @var list<array{interventionId:string,number:int|string,name:string,status:string,workItemId:string,action:string,workItemStatus:string,target:string}> $rows */
    $rows = $this->entityManager->createQueryBuilder()
      ->select('i.id AS interventionId, i.number, i.name, i.status, w.id AS workItemId, w.action, w.status AS workItemStatus, w.target')
      ->from(InterventionWorkItemRecord::class, 'w')->join('w.intervention', 'i')
      ->where('IDENTITY(i.organization) = :organization AND i.status NOT IN (:closed) AND w.status <> :skipped')
      ->andWhere('w.target = :target OR w.target LIKE :legacy')
      ->setParameter('organization', $organizationId)->setParameter('closed', ['published', 'abandoned'])->setParameter('skipped', 'skipped')
      ->setParameter('target', '/api/equipment/' . $equipmentId)->setParameter('legacy', '%' . $equipmentId . '%')
      ->orderBy('i.createdAt', 'ASC')->addOrderBy('w.id', 'ASC')->getQuery()->getArrayResult();
    $items = [];
    foreach ($rows as $row) {
      $legacy = json_decode($row['target'], true);
      if ('/api/equipment/' . $equipmentId !== $row['target'] && (!is_array($legacy) || $equipmentId !== ($legacy['equipmentId'] ?? null))) {
        continue;
      }
      $items[] = new InterventionEquipmentWork($row['interventionId'], (int) $row['number'], $row['name'], $row['status'], $row['workItemId'], $row['action'], $row['workItemStatus']);
    }

    return $items;
  }
}
