<?php

declare(strict_types=1);

namespace Intervention\Infrastructure\Adapter\Maintenance;

use DateTimeImmutable;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Intervention\Application\Contract\Draft\InterventionMaintenanceWork;
use Intervention\Application\Port\Inbound\InterventionMaintenanceWorkPort;
use Intervention\Domain\Exception\InterventionConflictException;
use Intervention\Infrastructure\Persistence\Doctrine\Record\{InterventionRecord, InterventionWorkItemRecord};

use function array_map;
use function in_array;
use function is_array;
use function is_string;
use function json_decode;
use function preg_match;

/**
 * Class InterventionMaintenanceWorkAdapter
 *
 * Restricts preventive handover reads and writes to the owning organization on main.
 *
 * @category Adapter
 */
final readonly class InterventionMaintenanceWorkAdapter implements InterventionMaintenanceWorkPort
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
   * Method status
   *
   * @access public
   *
   * @param string $organizationId owning organization
   * @param string $interventionId work order identifier
   *
   * @return ?string the scoped lifecycle status
   */
  public function status(string $organizationId, string $interventionId): ?string
  {
    $value = $this->entityManager->getConnection()->fetchOne('SELECT status FROM interventions WHERE id = :id AND organization_id = :organization', ['id' => $interventionId, 'organization' => $organizationId]);

    return is_string($value) ? $value : null;
  }

  /**
   * Method findOpenLegacyInspectionWork
   *
   * @access public
   *
   * @param string $organizationId owning organization
   * @param list<string> $equipmentIds prepared equipment scope
   *
   * @return array<string,list<InterventionMaintenanceWork>> work by equipment
   */
  public function findOpenLegacyInspectionWork(string $organizationId, array $equipmentIds): array
  {
    if ([] === $equipmentIds) {
      return [];
    }
    $query = $this->entityManager->createQueryBuilder()->select('w.id AS workItemId, w.target, i.id AS interventionId, i.number')
      ->from(InterventionWorkItemRecord::class, 'w')->join('w.intervention', 'i')
      ->where('IDENTITY(i.organization) = :organization AND i.status NOT IN (:closed) AND w.action = :action AND w.occurrenceId IS NULL AND w.status <> :skipped')
      ->setParameter('organization', $organizationId)->setParameter('closed', ['published', 'abandoned'])
      ->setParameter('action', 'inspection')->setParameter('skipped', 'skipped')
      ->setParameter('targets', array_map(static fn (string $id): string => '/api/equipment/' . $id, $equipmentIds))->orderBy('w.id', 'ASC');
    $targetConditions = ['w.target IN (:targets)'];
    foreach ($equipmentIds as $index => $equipmentId) {
      $targetConditions[] = 'w.target LIKE :legacy' . $index;
      $query->setParameter('legacy' . $index, '%' . $equipmentId . '%');
    }
    $query->andWhere($query->expr()->orX(...$targetConditions));
    /** @var list<array{workItemId:string,target:string,interventionId:string,number:int|string}> $rows */
    $rows = $query->getQuery()->getArrayResult();
    $result = [];
    foreach ($rows as $row) {
      $equipmentId = $this->equipmentId($row['target']);
      if (null !== $equipmentId && in_array($equipmentId, $equipmentIds, true)) {
        $result[$equipmentId][] = new InterventionMaintenanceWork($row['interventionId'], (int) $row['number'], $row['workItemId']);
      }
    }

    return $result;
  }

  /**
   * Method attachOccurrence
   *
   * Locks the owning order and task and preserves submitted publication revisions.
   *
   * @access public
   *
   * @param string $organizationId owning organization
   * @param string $workItemId prepared work identity
   * @param string $operationId source plan
   * @param string $occurrenceId source occurrence
   * @param string $operationKind control or maintenance
   *
   * @return void
   */
  public function attachOccurrence(string $organizationId, string $workItemId, string $operationId, string $occurrenceId, string $operationKind): void
  {
    if (!$this->entityManager->getConnection()->isTransactionActive()) {
      throw new InterventionConflictException('Preventive handover requires the main transaction.');
    }
    $item = $this->entityManager->find(InterventionWorkItemRecord::class, $workItemId);
    if (!$item instanceof InterventionWorkItemRecord || !$item->intervention instanceof InterventionRecord || $item->intervention->organization?->id !== $organizationId) {
      throw new InterventionConflictException('The preventive handover work is unavailable.');
    }
    $order = $item->intervention;
    $this->entityManager->lock($order, LockMode::PESSIMISTIC_WRITE);
    $this->entityManager->refresh($order);
    $this->entityManager->refresh($item, LockMode::PESSIMISTIC_WRITE);
    if ($item->occurrenceId === $occurrenceId && $item->operationId === $operationId && $item->operationKind === $operationKind) {
      return;
    }
    if (null !== $item->occurrenceId || in_array($order->status, ['submitted', 'published', 'abandoned'], true)
      || !in_array($operationKind, ['control', 'maintenance'], true) || ('control' === $operationKind ? 'inspection' : 'maintenance') !== $item->action) {
      throw new InterventionConflictException('The preventive handover conflicts with existing work.');
    }
    $item->operationId = $operationId;
    $item->occurrenceId = $occurrenceId;
    $item->operationKind = $operationKind;
    $equipmentId = $this->equipmentId($item->target);
    if (null === $equipmentId) {
      throw new InterventionConflictException('The legacy work does not identify its equipment.');
    }
    $item->target = '/api/equipment/' . $equipmentId;
    ++$item->revision;
    ++$order->revision;
    $item->updatedAt = $order->updatedAt = new DateTimeImmutable();
    $this->entityManager->flush();
  }

  /**
   * Method equipmentId
   *
   * Supports deployed JSON targets during handover without treating arbitrary text as an equipment reference.
   *
   * @access private
   *
   * @param ?string $target historical JSON or canonical equipment IRI
   *
   * @return ?string exact referenced equipment identity
   */
  private function equipmentId(?string $target): ?string
  {
    if (null === $target) {
      return null;
    }
    if (1 === preg_match('#^/api/equipment/([^/]+)$#', $target, $match)) {
      return $match[1];
    }
    $value = json_decode($target, true);

    return is_array($value) && is_string($value['equipmentId'] ?? null) ? $value['equipmentId'] : null;
  }
}
