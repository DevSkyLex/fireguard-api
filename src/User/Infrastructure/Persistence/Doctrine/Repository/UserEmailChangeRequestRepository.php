<?php

declare(strict_types=1);

namespace User\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Shared\Domain\ValueObject\Email;
use User\Application\Port\Outbound\EmailChangeRequestRepositoryPort;
use User\Domain\Model\EmailChange\{EmailChangeRequest, RestoredEmailChangeTimeline};
use User\Domain\ValueObject\UserId;
use User\Infrastructure\Persistence\Doctrine\Record\UserEmailChangeRequestRecord;

/**
 * Repository UserEmailChangeRequestRepository.
 *
 * Doctrine adapter for the email change request port (auth database).
 *
 * @category Repository
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UserEmailChangeRequestRepository implements EmailChangeRequestRepositoryPort
{
  // #region Constants
  /**
   * Constant UNCONFIRMED_PREDICATE.
   *
   * DQL condition shared by queries that select pending email change requests.
   *
   * @access private
   */
  private const string UNCONFIRMED_PREDICATE = 'r.confirmedAt IS NULL';
  // #endregion

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
  /**
   * Method save.
   *
   * Persists the request's current state in the auth database.
   *
   * @access public
   *
   * @param EmailChangeRequest $request the email change request aggregate
   *
   * @return void no return value
   */
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

  /**
   * Method findActiveByTokenHash.
   *
   * Finds an unconfirmed request matching the token hash that remains unexpired.
   *
   * @access public
   *
   * @param string $tokenHash hash of the confirmation token
   * @param DateTimeImmutable $now reference instant for expiry filtering
   *
   * @return ?EmailChangeRequest the matching active request, when found
   */
  public function findActiveByTokenHash(string $tokenHash, DateTimeImmutable $now): ?EmailChangeRequest
  {
    $record = $this->entityManager->createQueryBuilder()
      ->select('r')
      ->from(UserEmailChangeRequestRecord::class, 'r')
      ->where('r.tokenHash = :tokenHash')
      ->andWhere(self::UNCONFIRMED_PREDICATE)
      ->andWhere('r.expiresAt > :now')
      ->setParameter('tokenHash', $tokenHash)
      ->setParameter('now', $now)
      ->getQuery()
      ->getOneOrNullResult();

    return $record instanceof UserEmailChangeRequestRecord ? $this->toDomain($record) : null;
  }

  /**
   * Method confirmIfPending.
   *
   * Atomically confirms a pending, unexpired request if it still meets the database predicate.
   *
   * @access public
   *
   * @param string $requestId the request identifier
   * @param DateTimeImmutable $now confirmation instant and expiry boundary
   *
   * @return bool whether this call confirmed the request
   */
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
      ->andWhere(self::UNCONFIRMED_PREDICATE)
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

  /**
   * Method findActiveByUserId.
   *
   * Finds an unconfirmed, unexpired request for the user.
   *
   * @access public
   *
   * @param UserId $userId the user identifier
   * @param DateTimeImmutable $now reference instant for expiry filtering
   *
   * @return ?EmailChangeRequest the active request, when found
   */
  public function findActiveByUserId(UserId $userId, DateTimeImmutable $now): ?EmailChangeRequest
  {
    $record = $this->entityManager->createQueryBuilder()
      ->select('r')
      ->from(UserEmailChangeRequestRecord::class, 'r')
      ->where('r.userId = :userId')
      ->andWhere(self::UNCONFIRMED_PREDICATE)
      ->andWhere('r.expiresAt > :now')
      ->setParameter('userId', $userId->value)
      ->setParameter('now', $now)
      ->setMaxResults(1)
      ->getQuery()
      ->getOneOrNullResult();

    return $record instanceof UserEmailChangeRequestRecord ? $this->toDomain($record) : null;
  }

  /**
   * Method removePendingForUser.
   *
   * Deletes all unconfirmed requests belonging to the user.
   *
   * @access public
   *
   * @param UserId $userId the user identifier
   *
   * @return int number of requests deleted
   */
  public function removePendingForUser(UserId $userId): int
  {
    /** @var int */
    return $this->entityManager->createQueryBuilder()
      ->delete(UserEmailChangeRequestRecord::class, 'r')
      ->where('r.userId = :userId')
      ->andWhere(self::UNCONFIRMED_PREDICATE)
      ->setParameter('userId', $userId->value)
      ->getQuery()
      ->execute();
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
      timeline: new RestoredEmailChangeTimeline(
        requestedAt: $record->requestedAt,
        expiresAt: $record->expiresAt,
        confirmedAt: $record->confirmedAt,
      ),
    );
  }
  // #endregion
}
