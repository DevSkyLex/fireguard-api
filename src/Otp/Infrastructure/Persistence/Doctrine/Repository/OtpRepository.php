<?php

declare(strict_types=1);

namespace Otp\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Otp\Application\Port\Outbound\OtpRepositoryPort;
use Otp\Domain\Model\Otp;
use Otp\Domain\ValueObject\OtpId;
use Otp\Domain\ValueObject\OtpPurpose;
use Otp\Infrastructure\Persistence\Doctrine\Mapper\OtpMapper;
use Otp\Infrastructure\Persistence\Doctrine\Record\OtpRecord;

use function is_int;

/**
 * Repository OtpRepository.
 *
 * @category Repository
 *
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
     * @param OtpMapper              $mapper        the mapper
     */
    public function __construct(
        private EntityManagerInterface $entityManager,
        private OtpMapper $mapper,
    ) {
    }
    // #endregion

    // #region Methods
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

    public function findById(OtpId $id): ?Otp
    {
        $repository = $this->entityManager->getRepository(OtpRecord::class);
        $record = $repository->find($id->value);

        if (null === $record) {
            return null;
        }

        return $this->mapper->toDomain($record);
    }

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

    public function findByChallengeToken(\Otp\Domain\ValueObject\ChallengeToken $token): ?Otp
    {
        $repository = $this->entityManager->getRepository(OtpRecord::class);

        /** @var OtpRecord|null $record */
        $record = $repository->findOneBy(['challengeToken' => $token->value]);

        if (null === $record) {
            return null;
        }

        return $this->mapper->toDomain($record);
    }

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
