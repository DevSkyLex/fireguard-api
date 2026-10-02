<?php

declare(strict_types=1);

namespace Otp\Infrastructure\Persistence\Doctrine\Repository;

use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Otp\Application\Port\Inbound\Totp\TotpEnrollmentPurgePort;
use Otp\Application\Port\Outbound\Totp\TotpEnrollmentRepositoryPort;
use Otp\Domain\Model\Totp\TotpEnrollment;
use Otp\Infrastructure\Persistence\Doctrine\Mapper\TotpEnrollmentMapper;
use Otp\Infrastructure\Persistence\Doctrine\Record\TotpEnrollmentRecord;
use Throwable;

/**
 * Repository TotpEnrollmentRepository.
 *
 * @category Repository
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class TotpEnrollmentRepository implements TotpEnrollmentRepositoryPort, TotpEnrollmentPurgePort
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @param EntityManagerInterface $entityManager the entity manager
   * @param TotpEnrollmentMapper $mapper the mapper
   */
  public function __construct(
    private EntityManagerInterface $entityManager,
    private TotpEnrollmentMapper $mapper,
  ) {
  }

  // #endregion
  // #region Methods
  /**
   * Method withUserLock.
   * {@inheritDoc}
   */
  public function withUserLock(string $userId, callable $operation): mixed
  {
    try {
      return $this->entityManager->getConnection()->transactional(function () use ($userId, $operation): mixed {
        $this->entityManager->getConnection()->executeQuery('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', ['fireguard.otp.user:' . $userId]);

        return $operation();
      });
    } catch (Throwable $exception) {
      $this->entityManager->clear();

      throw $exception;
    }
  }

  /**
   * Method purgeForUser.
   * {@inheritDoc}
   */
  public function purgeForUser(string $userId): void
  {
    $this->withUserLock($userId, function () use ($userId): void {
      $this->entityManager->getConnection()->executeStatement('DELETE FROM totp_enrollments WHERE user_id = ?', [$userId]);
    });
  }

  /**
   * Method save
   *
   * Persists the enrollment through its owning auth connection.
   *
   * @access public
   *
   * @param TotpEnrollment $enrollment the enrollment
   *
   * @return void
   */
  public function save(TotpEnrollment $enrollment): void
  {
    $repository = $this->entityManager->getRepository(TotpEnrollmentRecord::class);
    $existingRecord = $repository->find($enrollment->userId());

    $record = $this->mapper->toRecord($enrollment, $existingRecord);

    if (null === $existingRecord) {
      $this->entityManager->persist($record);
    }

    $this->entityManager->flush();
  }

  /**
   * Method findByUserId
   *
   * Finds by user id using the supplied criteria.
   *
   * @access public
   *
   * @param string $userId the user identifier
   *
   * @return ?TotpEnrollment
   */
  public function findByUserId(string $userId): ?TotpEnrollment
  {
    $repository = $this->entityManager->getRepository(TotpEnrollmentRecord::class);
    $record = $repository->find($userId);

    if (null === $record) {
      return null;
    }

    $lockMode = $this->entityManager->getConnection()->isTransactionActive() ? LockMode::PESSIMISTIC_WRITE : null;
    $this->entityManager->refresh($record, $lockMode);

    return $this->mapper->toDomain($record);
  }
  // #endregion
}
