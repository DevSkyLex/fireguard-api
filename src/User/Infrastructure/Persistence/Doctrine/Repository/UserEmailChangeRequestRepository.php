<?php

declare(strict_types=1);

namespace User\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Shared\Domain\ValueObject\Email;
use User\Application\Port\Outbound\EmailChangeRequestRepositoryPort;
use User\Domain\Model\EmailChange\EmailChangeRequest;
use User\Domain\ValueObject\UserId;
use User\Infrastructure\Persistence\Doctrine\Record\UserEmailChangeRequestRecord;

/**
 * Repository UserEmailChangeRequestRepository.
 *
 * Doctrine adapter for the email change request port (auth database).
 *
 * @category Repository
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UserEmailChangeRequestRepository implements EmailChangeRequestRepositoryPort
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * UserEmailChangeRequestRepository class.
   *
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager the auth entity manager
   */
  public function __construct(
    private EntityManagerInterface $entityManager,
  ) {
  }
  // #endregion

  // #region Methods
  public function save(EmailChangeRequest $request): void
  {
    $record = $this->entityManager->find(UserEmailChangeRequestRecord::class, $request->id());

    if (null === $record) {
      $record = new UserEmailChangeRequestRecord();
      $record->id = $request->id();
      $this->entityManager->persist($record);
    }

    $record->userId = $request->userId()->value;
    $record->currentEmail = $request->currentEmail()->value;
    $record->newEmail = $request->newEmail()->value;
    $record->tokenHash = $request->tokenHash();
    $record->requestedAt = $request->requestedAt();
    $record->expiresAt = $request->expiresAt();
    $record->confirmedAt = $request->confirmedAt();

    $this->entityManager->flush();
  }

  public function findActiveByTokenHash(string $tokenHash, DateTimeImmutable $now): ?EmailChangeRequest
  {
    $record = $this->entityManager->createQueryBuilder()
      ->select('r')
      ->from(UserEmailChangeRequestRecord::class, 'r')
      ->where('r.tokenHash = :tokenHash')
      ->andWhere('r.confirmedAt IS NULL')
      ->andWhere('r.expiresAt > :now')
      ->setParameter('tokenHash', $tokenHash)
      ->setParameter('now', $now)
      ->getQuery()
      ->getOneOrNullResult();

    return $record instanceof UserEmailChangeRequestRecord ? $this->toDomain($record) : null;
  }

  public function confirmIfPending(string $requestId, DateTimeImmutable $now): bool
  {
    // Single conditional UPDATE — the WHERE clause re-checks the pending
    // state inside the database, so two concurrent confirmations of the
    // same token cannot both succeed: exactly one updates a row.
    /** @var int $updated */
    $updated = $this->entityManager->createQueryBuilder()
      ->update(UserEmailChangeRequestRecord::class, 'r')
      ->set('r.confirmedAt', ':now')
      ->where('r.id = :id')
      ->andWhere('r.confirmedAt IS NULL')
      ->andWhere('r.expiresAt > :now')
      ->setParameter('id', $requestId)
      ->setParameter('now', $now)
      ->getQuery()
      ->execute();

    if (1 !== $updated) {
      return false;
    }

    // The DQL UPDATE bypasses the unit of work: refresh any managed copy
    // so a later flush cannot silently write the stale NULL back.
    $record = $this->entityManager->getUnitOfWork()
      ->tryGetById($requestId, UserEmailChangeRequestRecord::class);
    if ($record instanceof UserEmailChangeRequestRecord) {
      $record->confirmedAt = $now;
    }

    return true;
  }

  public function findActiveByUserId(UserId $userId, DateTimeImmutable $now): ?EmailChangeRequest
  {
    $record = $this->entityManager->createQueryBuilder()
      ->select('r')
      ->from(UserEmailChangeRequestRecord::class, 'r')
      ->where('r.userId = :userId')
      ->andWhere('r.confirmedAt IS NULL')
      ->andWhere('r.expiresAt > :now')
      ->setParameter('userId', $userId->value)
      ->setParameter('now', $now)
      ->setMaxResults(1)
      ->getQuery()
      ->getOneOrNullResult();

    return $record instanceof UserEmailChangeRequestRecord ? $this->toDomain($record) : null;
  }

  public function removePendingForUser(UserId $userId): int
  {
    /** @var int $deleted */
    $deleted = $this->entityManager->createQueryBuilder()
      ->delete(UserEmailChangeRequestRecord::class, 'r')
      ->where('r.userId = :userId')
      ->andWhere('r.confirmedAt IS NULL')
      ->setParameter('userId', $userId->value)
      ->getQuery()
      ->execute();

    return $deleted;
  }

  /**
   * Method toDomain.
   *
   * Rehydrates the domain model from a record.
   *
   * @since 1.0.0
   *
   * @param UserEmailChangeRequestRecord $record the record
   *
   * @return EmailChangeRequest the domain model
   */
  private function toDomain(UserEmailChangeRequestRecord $record): EmailChangeRequest
  {
    return EmailChangeRequest::restore(
      id: $record->id,
      userId: new UserId($record->userId),
      currentEmail: new Email($record->currentEmail),
      newEmail: new Email($record->newEmail),
      tokenHash: $record->tokenHash,
      requestedAt: $record->requestedAt,
      expiresAt: $record->expiresAt,
      confirmedAt: $record->confirmedAt,
    );
  }
  // #endregion
}
