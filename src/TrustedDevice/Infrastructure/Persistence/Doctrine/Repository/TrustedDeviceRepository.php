<?php

declare(strict_types=1);

namespace TrustedDevice\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use TrustedDevice\Application\Port\Outbound\TrustedDeviceRepositoryPort;
use TrustedDevice\Domain\Model\TrustedDevice\TrustedDevice;
use TrustedDevice\Domain\ValueObject\TrustedDeviceId;
use TrustedDevice\Infrastructure\Persistence\Doctrine\Mapper\TrustedDeviceMapper;
use TrustedDevice\Infrastructure\Persistence\Doctrine\Record\TrustedDeviceRecord;

use function array_map;
use function is_int;

/**
 * Repository TrustedDeviceRepository.
 */
final readonly class TrustedDeviceRepository implements TrustedDeviceRepositoryPort
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Stores trusted-device aggregates through the configured Doctrine entity manager and mapper.
   *
   * @access public
   *
   * @param EntityManagerInterface $entityManager the entity manager
   * @param TrustedDeviceMapper $mapper the mapper
   *
   * @return void
   */
  public function __construct(
    private EntityManagerInterface $entityManager,
    private TrustedDeviceMapper $mapper,
  ) {
  }

  // #endregion

  // #region Methods
  /**
   * Method save
   *
   * Persists the trusted-device aggregate, updating its existing row when one is present.
   *
   * @access public
   *
   * @param TrustedDevice $device the device
   *
   * @return void
   */
  public function save(TrustedDevice $device): void
  {
    $repository = $this->entityManager->getRepository(TrustedDeviceRecord::class);
    $existing = $repository->find($device->id()->value);

    $record = $this->mapper->toRecord($device, $existing);

    if (null === $existing) {
      $this->entityManager->persist($record);
    }

    $this->entityManager->flush();
  }

  /**
   * Method findById
   *
   * Loads the trusted-device record by its identifier and maps it to the domain aggregate.
   *
   * @access public
   *
   * @param TrustedDeviceId $id the identifier
   *
   * @return ?TrustedDevice the matching active device, or null when missing, revoked or expired
   */
  public function findById(TrustedDeviceId $id): ?TrustedDevice
  {
    $record = $this->entityManager->getRepository(TrustedDeviceRecord::class)->find($id->value);

    return $record ? $this->mapper->toDomain($record) : null;
  }

  /**
   * Method findByUserIdAndFingerprint
   *
   * Finds the device matching both the owner identifier and stable fingerprint hash.
   *
   * @access public
   *
   * @param string $userId the user identifier
   * @param string $fingerprint the fingerprint
   *
   * @return ?TrustedDevice the matching active device, or null when missing, revoked or expired
   */
  public function findByUserIdAndFingerprint(string $userId, string $fingerprint): ?TrustedDevice
  {
    $record = $this->entityManager->getRepository(TrustedDeviceRecord::class)
      ->findOneBy(['userId' => $userId, 'fingerprint' => $fingerprint]);

    return $record ? $this->mapper->toDomain($record) : null;
  }

  /**
   * Method findByToken
   *
   * Looks up a token hash and returns a device only while it is unrevoked and unexpired.
   *
   * @access public
   *
   * @param string $tokenHash the one-way hash of the presented trusted-device token
   *
   * @return ?TrustedDevice the matching active device, or null when missing, revoked or expired
   */
  public function findByToken(string $tokenHash): ?TrustedDevice
  {
    $record = $this->entityManager->getRepository(TrustedDeviceRecord::class)
      ->findOneBy(['tokenHash' => $tokenHash, 'revoked' => false]);

    if (!$record || $record->getExpiresAt() < new DateTimeImmutable()) {
      return null;
    }

    return $this->mapper->toDomain($record);
  }

  /**
   * Method findAllByUserId
   *
   * Returns the unrevoked trusted-device records owned by the specified user.
   *
   * @access public
   *
   * @param string $userId the user identifier
   *
   * @return list<TrustedDevice> the unrevoked devices for that user
   */
  public function findAllByUserId(string $userId): array
  {
    $records = $this->entityManager->getRepository(TrustedDeviceRecord::class)
      ->findBy(['userId' => $userId, 'revoked' => false]);

    return array_map(fn ($r) => $this->mapper->toDomain($r), $records);
  }

  /**
   * Method revokeAllForUser
   *
   * Marks every currently unrevoked device for the user as revoked and returns the affected row count.
   *
   * @access public
   *
   * @param string $userId the user identifier
   *
   * @return int the number of records newly marked revoked
   */
  public function revokeAllForUser(string $userId): int
  {
    $result = $this->entityManager->createQueryBuilder()
      ->update(TrustedDeviceRecord::class, 'd')
      ->set('d.revoked', ':true')
      ->where('d.userId = :userId')
      ->andWhere('d.revoked = :false')
      ->setParameter('userId', $userId)
      ->setParameter('true', true)
      ->setParameter('false', false)
      ->getQuery()
      ->execute();

    return is_int($result) ? $result : 0;
  }

  /**
   * Method delete
   *
   * Physically removes the record for the specified trusted-device identifier when it exists.
   *
   * @access public
   *
   * @param TrustedDeviceId $id the identifier
   *
   * @return void
   */
  public function delete(TrustedDeviceId $id): void
  {
    $record = $this->entityManager->getRepository(TrustedDeviceRecord::class)->find($id->value);
    if ($record) {
      $this->entityManager->remove($record);
      $this->entityManager->flush();
    }
  }
  // #endregion
}
