<?php

declare(strict_types=1);

namespace Equipment\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\{EntityManagerInterface, Query};
use Equipment\Application\Contract\Replacement\EquipmentReplacementReceipt;
use Equipment\Application\Port\Outbound\EquipmentReplacementRepositoryPort;
use Equipment\Domain\Model\Replacement\{EquipmentReplacement, ReplacementEquipment};
use Equipment\Infrastructure\Persistence\Doctrine\Record\{EquipmentRecord, EquipmentReplacementReceiptRecord};
use LogicException;

use function array_unique;
use function sort;

/**
 * Class EquipmentReplacementRepository
 *
 * Persists validated replacements through ORM so published-change outboxes share the transaction.
 *
 * @category Repository
 */
final readonly class EquipmentReplacementRepository implements EquipmentReplacementRepositoryPort
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param EntityManagerInterface $entityManager the explicitly wired main entity manager
   *
   * @return void
   */
  public function __construct(private EntityManagerInterface $entityManager)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method findReceiptForUpdate
   *
   * @access public
   *
   * @param string $organizationId the owner
   * @param string $clientOperationId the stable operation identity
   *
   * @return ?EquipmentReplacementReceipt the stored result
   */
  public function findReceiptForUpdate(string $organizationId, string $clientOperationId): ?EquipmentReplacementReceipt
  {
    $this->entityManager->getConnection()->executeQuery(
      'SELECT pg_advisory_xact_lock(hashtextextended(:identity, 0))',
      ['identity' => 'equipment-replacement:' . $organizationId . ':' . $clientOperationId],
    );
    $record = $this->entityManager->find(EquipmentReplacementReceiptRecord::class, [
      'organizationId' => $organizationId, 'clientOperationId' => $clientOperationId,
    ]);

    return $record instanceof EquipmentReplacementReceiptRecord ? new EquipmentReplacementReceipt(
      $record->organizationId,
      $record->clientOperationId,
      $record->predecessorEquipmentId,
      $record->successorEquipmentId,
      $record->payloadHash,
    ) : null;
  }

  /**
   * Method lockEquipment
   *
   * @access public
   *
   * @param string $organizationId the owner
   * @param list<string> $equipmentIds the identities to lock
   *
   * @return array<string, ReplacementEquipment> current owner-scoped candidates
   */
  public function lockEquipment(string $organizationId, array $equipmentIds): array
  {
    $equipmentIds = array_unique($equipmentIds);
    sort($equipmentIds);
    $query = $this->entityManager->createQueryBuilder()
      ->select('equipment')->from(EquipmentRecord::class, 'equipment')
      ->where('IDENTITY(equipment.organization) = :organization')
      ->andWhere('equipment.id IN (:ids)')
      ->orderBy('equipment.id', 'ASC')
      ->setParameter('organization', $organizationId)
      ->setParameter('ids', $equipmentIds)
      ->getQuery()->setLockMode(LockMode::PESSIMISTIC_WRITE)
      ->setHint(Query::HINT_REFRESH, true);

    /** @var list<EquipmentRecord> $records */
    $records = $query->getResult();
    $candidates = [];
    foreach ($records as $record) {
      $candidates[$record->id] = new ReplacementEquipment(
        $record->id,
        $organizationId,
        $record->recordStatus,
        $record->status,
        $record->facilityId,
        $record->locationLabel,
        $record->planPosition,
        $record->predecessorEquipmentId,
        $record->successorEquipmentId,
      );
    }

    return $candidates;
  }

  /**
   * Method save
   *
   * @access public
   *
   * @param EquipmentReplacement $replacement the validated replacement
   * @param EquipmentReplacementReceipt $receipt the durable replay record
   *
   * @return void
   */
  public function save(EquipmentReplacement $replacement, EquipmentReplacementReceipt $receipt): void
  {
    $predecessor = $this->entityManager->find(EquipmentRecord::class, $replacement->predecessor->id);
    $successor = $this->entityManager->find(EquipmentRecord::class, $replacement->successor->id);
    if (!$predecessor instanceof EquipmentRecord || !$successor instanceof EquipmentRecord) {
      throw new LogicException('Locked replacement equipment is unavailable.');
    }
    $now = new DateTimeImmutable();
    $predecessor->status = 'decommissioned';
    $predecessor->successorEquipmentId = $successor->id;
    $predecessor->updatedAt = $now;
    $successor->predecessorEquipmentId = $predecessor->id;
    $successor->facilityId = $predecessor->facilityId;
    $successor->locationLabel = $predecessor->locationLabel;
    $successor->planPosition = $predecessor->planPosition;
    $successor->status = $replacement->successorStatus();
    $successor->updatedAt = $now;
    if ('operational' === $successor->status) {
      $successor->commissionedAt ??= $now;
      $successor->installedAt ??= $now;
    }
    $record = new EquipmentReplacementReceiptRecord();
    $record->organizationId = $receipt->organizationId;
    $record->clientOperationId = $receipt->clientOperationId;
    $record->predecessorEquipmentId = $receipt->predecessorEquipmentId;
    $record->successorEquipmentId = $receipt->successorEquipmentId;
    $record->payloadHash = $receipt->payloadHash;
    $record->createdAt = $now;
    $this->entityManager->persist($record);
    $this->entityManager->flush();
  }
  // #endregion
}
