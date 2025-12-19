<?php

declare(strict_types=1);

namespace TrustedDevice\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use TrustedDevice\Application\Port\Outbound\TrustedDeviceRepositoryPort;
use TrustedDevice\Domain\Model\TrustedDevice;
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
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TrustedDeviceMapper $mapper,
    ) {
    }

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

    public function findById(TrustedDeviceId $id): ?TrustedDevice
    {
        $record = $this->entityManager->getRepository(TrustedDeviceRecord::class)->find($id->value);

        return $record ? $this->mapper->toDomain($record) : null;
    }

    public function findByUserIdAndFingerprint(string $userId, string $fingerprint): ?TrustedDevice
    {
        $record = $this->entityManager->getRepository(TrustedDeviceRecord::class)
          ->findOneBy(['userId' => $userId, 'fingerprint' => $fingerprint]);

        return $record ? $this->mapper->toDomain($record) : null;
    }

    public function findByToken(string $tokenHash): ?TrustedDevice
    {
        $record = $this->entityManager->getRepository(TrustedDeviceRecord::class)
          ->findOneBy(['tokenHash' => $tokenHash, 'revoked' => false]);

        if (!$record || $record->getExpiresAt() < new DateTimeImmutable()) {
            return null;
        }

        return $this->mapper->toDomain($record);
    }

    public function findAllByUserId(string $userId): array
    {
        $records = $this->entityManager->getRepository(TrustedDeviceRecord::class)
          ->findBy(['userId' => $userId, 'revoked' => false]);

        return array_map(fn ($r) => $this->mapper->toDomain($r), $records);
    }

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

    public function delete(TrustedDeviceId $id): void
    {
        $record = $this->entityManager->getRepository(TrustedDeviceRecord::class)->find($id->value);
        if ($record) {
            $this->entityManager->remove($record);
            $this->entityManager->flush();
        }
    }
}
