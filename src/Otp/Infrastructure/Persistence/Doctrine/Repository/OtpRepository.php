<?php

declare(strict_types=1);

namespace Otp\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Otp\Application\Port\Outbound\Challenge\OtpRepositoryPort;
use Otp\Domain\Model\Otp;
use Otp\Domain\ValueObject\{OtpId, OtpPurpose};
use Otp\Infrastructure\Persistence\Doctrine\Mapper\OtpMapper;
use Otp\Infrastructure\Persistence\Doctrine\Record\OtpRecord;

use function is_int;

/**
 * Repository OtpRepository.
 *
 * @category Repository
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OtpRepository implements OtpRepositoryPort
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @param EntityManagerInterface $entityManager the entity manager
   * @param OtpMapper $mapper the mapper
   */
  public function __construct(
    private EntityManagerInterface $entityManager,
    private OtpMapper $mapper,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method save.
   *
   * Persists an OTP aggregate, reusing the existing record when available.
   *
   * @access public
   *
   * @param Otp $otp the OTP aggregate
   *
   * @return void no return value
   */
  public function save(Otp $otp): void
  {
    $repository = $this->entityManager->getRepository(OtpRecord::class);
    $existingRecord = $repository->find($otp->id()->value);

    $record = $this->mapper->toRecord($otp, $existingRecord);

    if (null === $existingRecord) {
      $this->entityManager->persist($record);
    }

    $this->entityManager->flush();
  }

  /**
   * Method findById.
   *
   * Loads an OTP aggregate by its identifier.
   *
   * @access public
   *
   * @param OtpId $id the OTP identifier
   *
   * @return ?Otp the OTP aggregate when found
   */
  public function findById(OtpId $id): ?Otp
  {
    $repository = $this->entityManager->getRepository(OtpRecord::class);
    $record = $repository->find($id->value);

    if (null === $record) {
      return null;
    }

    return $this->mapper->toDomain($record);
  }

  /**
   * Method findActiveByUserAndPurpose.
   *
   * Finds the newest unverified OTP for a user and purpose that has not expired.
   *
   * @access public
   *
   * @param string $userId the user identifier
   * @param OtpPurpose $purpose the OTP purpose
   *
   * @return ?Otp the matching active OTP, when present
   */
  public function findActiveByUserAndPurpose(string $userId, OtpPurpose $purpose): ?Otp
  {
    $repository = $this->entityManager->getRepository(OtpRecord::class);

    $record = $repository->createQueryBuilder('o')
      ->where('o.userId = :userId')
      ->andWhere('o.purpose = :purpose')
      ->andWhere('o.expiresAt > :now')
      ->andWhere('o.verifiedAt IS NULL')
      ->setParameter('userId', $userId)
      ->setParameter('purpose', $purpose->value)
      ->setParameter('now', new DateTimeImmutable())
      ->orderBy('o.createdAt', 'DESC')
      ->setMaxResults(1)
      ->getQuery()
      ->getOneOrNullResult();

    if (!$record instanceof OtpRecord) {
      return null;
    }

    return $this->mapper->toDomain($record);
  }

  /**
   * Method findByChallengeToken.
   *
   * Finds an OTP by its challenge token, locking and refreshing the row inside a transaction.
   *
   * @access public
   *
   * @param \Otp\Domain\ValueObject\ChallengeToken $token the challenge token
   *
   * @return ?Otp the matching OTP, when present
   */
  public function findByChallengeToken(\Otp\Domain\ValueObject\ChallengeToken $token): ?Otp
  {
    $repository = $this->entityManager->getRepository(OtpRecord::class);

    $query = $repository->createQueryBuilder('o')
      ->where('o.challengeToken = :token')
      ->setParameter('token', $token->value)
      ->getQuery();
    if ($this->entityManager->getConnection()->isTransactionActive()) {
      $query->setLockMode(LockMode::PESSIMISTIC_WRITE);
      $query->setHint(\Doctrine\ORM\Query::HINT_REFRESH, true);
    }
    /** @var OtpRecord|null $record */
    $record = $query->getOneOrNullResult();

    if (null === $record) {
      return null;
    }

    return $this->mapper->toDomain($record);
  }

  /**
   * Method revokeAllForUser.
   *
   * Expires all active, unverified OTPs for the user and purpose.
   *
   * @access public
   *
   * @param string $userId the user identifier
   * @param OtpPurpose $purpose the OTP purpose
   *
   * @return int number of records updated
   */
  public function revokeAllForUser(string $userId, OtpPurpose $purpose): int
  {
    // Set expires_at to now for all active OTPs
    $result = $this->entityManager->createQueryBuilder()
      ->update(OtpRecord::class, 'o')
      ->set('o.expiresAt', ':now')
      ->where('o.userId = :userId')
      ->andWhere('o.purpose = :purpose')
      ->andWhere('o.expiresAt > :now')
      ->andWhere('o.verifiedAt IS NULL')
      ->setParameter('userId', $userId)
      ->setParameter('purpose', $purpose->value)
      ->setParameter('now', new DateTimeImmutable())
      ->getQuery()
      ->execute();

    return is_int($result) ? $result : 0;
  }
  // #endregion
}
