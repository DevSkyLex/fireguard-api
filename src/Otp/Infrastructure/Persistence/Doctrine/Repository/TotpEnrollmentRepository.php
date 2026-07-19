<?php

declare(strict_types=1);

namespace Otp\Infrastructure\Persistence\Doctrine\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Otp\Application\Port\Outbound\Totp\TotpEnrollmentRepositoryPort;
use Otp\Domain\Model\Totp\TotpEnrollment;
use Otp\Infrastructure\Persistence\Doctrine\Mapper\TotpEnrollmentMapper;
use Otp\Infrastructure\Persistence\Doctrine\Record\TotpEnrollmentRecord;

/**
 * Repository TotpEnrollmentRepository.
 *
 * @category Repository
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class TotpEnrollmentRepository implements TotpEnrollmentRepositoryPort
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

  public function findByUserId(string $userId): ?TotpEnrollment
  {
    $repository = $this->entityManager->getRepository(TotpEnrollmentRecord::class);
    $record = $repository->find($userId);

    if (null === $record) {
      return null;
    }

    return $this->mapper->toDomain($record);
  }
  // #endregion
}
