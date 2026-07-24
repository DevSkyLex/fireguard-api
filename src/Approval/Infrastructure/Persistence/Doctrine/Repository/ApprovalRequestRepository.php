<?php

declare(strict_types=1);

namespace Approval\Infrastructure\Persistence\Doctrine\Repository;

use Approval\Application\Contract\Reservation\ApprovalReservation;
use Approval\Application\Port\Outbound\ApprovalRequestRepositoryPort;
use Approval\Domain\Model\ApprovalRequest\ApprovalRequest;
use Approval\Domain\ValueObject\{ApprovalRequestId, ApprovalStatus};
use Approval\Infrastructure\Persistence\Doctrine\Mapper\ApprovalRequestMapper;
use Approval\Infrastructure\Persistence\Doctrine\Record\ApprovalRequestRecord;
use DateTimeImmutable;
use Doctrine\ORM\{EntityManagerInterface, EntityRepository};

use function array_map;
use function is_string;
use function json_encode;

use const JSON_THROW_ON_ERROR;

/**
 * Repository ApprovalRequestRepository.
 *
 * @category Repository
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ApprovalRequestRepository implements ApprovalRequestRepositoryPort
{
  // #region Properties
  /**
   * @var EntityRepository<ApprovalRequestRecord>
   */
  private EntityRepository $repository;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager the Doctrine entity manager
   */
  public function __construct(
    private EntityManagerInterface $entityManager,
  ) {
    $this->repository = $this->entityManager->getRepository(ApprovalRequestRecord::class);
  }
  // #endregion

  // #region Methods
  public function reservePending(
    string $id,
    string $organizationId,
    string $actionType,
    string $subjectId,
    string $requestedByMemberId,
    string $requestedByUserId,
    array $payload,
    DateTimeImmutable $expiresAt,
    DateTimeImmutable $now,
  ): ApprovalReservation {
    // A raw DBAL statement — not the ORM's persist()/flush() — is used
    // deliberately: a unique-constraint violation during an ORM flush()
    // closes the EntityManager (see
    // `Automation\Infrastructure\Persistence\Doctrine\Repository\AutomationRunRepository::reserveRun()`
    // for the same concern and precedent). A duplicate claim of the
    // partial unique index on `status = 'pending'` is an expected, routine
    // outcome — the concurrent/prior pending request — not an exceptional
    // one.
    // ON CONFLICT DO NOTHING makes a duplicate claim of the partial unique index
    // on `status = 'pending'` a no-op returning zero affected rows — no
    // exception, so the surrounding transaction is never aborted (catching the
    // violation instead poisons it on PostgreSQL). Zero rows means a prior
    // pending request won the race: return its id, not newly created.
    $inserted = $this->entityManager->getConnection()->executeStatement(
      'INSERT INTO approval_requests '
      . '(id, organization_id, action_type, subject_id, status, requested_by_member_id, requested_by_user_id, payload, expires_at, created_at, updated_at) '
      . 'VALUES (:id, :organizationId, :actionType, :subjectId, :status, :requestedByMemberId, :requestedByUserId, :payload, :expiresAt, :createdAt, :updatedAt) '
      . 'ON CONFLICT DO NOTHING',
      [
        'id' => $id,
        'organizationId' => $organizationId,
        'actionType' => $actionType,
        'subjectId' => $subjectId,
        'status' => ApprovalStatus::PENDING->value,
        'requestedByMemberId' => $requestedByMemberId,
        'requestedByUserId' => $requestedByUserId,
        'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
        'expiresAt' => $expiresAt,
        'createdAt' => $now,
        'updatedAt' => $now,
      ],
      [
        'expiresAt' => 'datetime_immutable',
        'createdAt' => 'datetime_immutable',
        'updatedAt' => 'datetime_immutable',
      ],
    );

    if (0 === $inserted) {
      $existingId = $this->findExistingPendingId($organizationId, $actionType, $subjectId);

      return new ApprovalReservation($existingId ?? $id, false);
    }

    return new ApprovalReservation($id, true);
  }

  public function save(ApprovalRequest $request): void
  {
    $record = $this->repository->find((string) $request->id());

    if (!$record instanceof ApprovalRequestRecord) {
      $record = new ApprovalRequestRecord();
      $this->entityManager->persist($record);
    }

    ApprovalRequestMapper::toRecord($request, $record);

    $this->entityManager->flush();
  }

  public function findById(ApprovalRequestId $id): ?ApprovalRequest
  {
    $record = $this->repository->find((string) $id);

    return $record instanceof ApprovalRequestRecord ? ApprovalRequestMapper::toDomain($record) : null;
  }

  public function listByOrganization(string $organizationId, ?string $status, ?string $actionType, int $limit, int $offset): array
  {
    $qb = $this->repository->createQueryBuilder('r')
      ->where('r.organizationId = :organizationId')
      ->setParameter('organizationId', $organizationId)
      ->orderBy('r.createdAt', 'DESC')
      ->addOrderBy('r.id', 'DESC')
      ->setFirstResult($offset)
      ->setMaxResults($limit);

    if (null !== $status) {
      $qb->andWhere('r.status = :status')->setParameter('status', $status);
    }

    if (null !== $actionType) {
      $qb->andWhere('r.actionType = :actionType')->setParameter('actionType', $actionType);
    }

    /** @var list<ApprovalRequestRecord> $records */
    $records = $qb->getQuery()->getResult();

    return array_map(ApprovalRequestMapper::toDomain(...), $records);
  }

  public function countByOrganization(string $organizationId, ?string $status, ?string $actionType): int
  {
    $qb = $this->repository->createQueryBuilder('r')
      ->select('COUNT(r.id)')
      ->where('r.organizationId = :organizationId')
      ->setParameter('organizationId', $organizationId);

    if (null !== $status) {
      $qb->andWhere('r.status = :status')->setParameter('status', $status);
    }

    if (null !== $actionType) {
      $qb->andWhere('r.actionType = :actionType')->setParameter('actionType', $actionType);
    }

    return (int) $qb->getQuery()->getSingleScalarResult();
  }

  public function findPendingExpiredBefore(DateTimeImmutable $now, int $limit): array
  {
    /** @var list<ApprovalRequestRecord> $records */
    $records = $this->repository->createQueryBuilder('r')
      ->where('r.status = :status')
      ->andWhere('r.expiresAt <= :now')
      ->setParameter('status', ApprovalStatus::PENDING->value)
      ->setParameter('now', $now)
      ->orderBy('r.expiresAt', 'ASC')
      ->setMaxResults($limit)
      ->getQuery()
      ->getResult();

    return array_map(ApprovalRequestMapper::toDomain(...), $records);
  }

  /**
   * Method findExistingPendingId.
   *
   * Raw SELECT resolving the identifier of the already-pending request for
   * a `(organization, action type, subject)` tuple after a reservation
   * conflict.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the owning organization identifier
   * @param string $actionType the regulated action type
   * @param string $subjectId the acted-upon subject identifier
   *
   * @return ?string the pending request identifier, when found
   */
  private function findExistingPendingId(string $organizationId, string $actionType, string $subjectId): ?string
  {
    $id = $this->entityManager->getConnection()->fetchOne(
      'SELECT id FROM approval_requests WHERE organization_id = :organizationId AND action_type = :actionType AND subject_id = :subjectId AND status = :status LIMIT 1',
      [
        'organizationId' => $organizationId,
        'actionType' => $actionType,
        'subjectId' => $subjectId,
        'status' => ApprovalStatus::PENDING->value,
      ],
    );

    return is_string($id) ? $id : null;
  }
  // #endregion
}
