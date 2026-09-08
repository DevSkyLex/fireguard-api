<?php

declare(strict_types=1);

namespace Auth\Infrastructure\Persistence\Doctrine\Repository;

use Auth\Application\Contract\Federation\FederatedConnection;
use Auth\Application\Port\Outbound\Federation\FederatedIdentityRepositoryPort;
use Auth\Domain\ValueObject\Federation\FederatedProvider;
use Auth\Infrastructure\Persistence\Doctrine\Record\FederatedIdentityRecord;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

use function array_map;
use function count;

/**
 * Repository FederatedIdentityRepository.
 *
 * @category Repository
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FederatedIdentityRepository implements FederatedIdentityRepositoryPort
{
  public function __construct(private EntityManagerInterface $entityManager)
  {
  }

  public function findBySubject(FederatedProvider $provider, string $subject): ?FederatedConnection
  {
    $record = $this->entityManager->getRepository(FederatedIdentityRecord::class)->findOneBy([
      'provider' => $provider->value,
      'subject' => $subject,
    ]);

    return $record instanceof FederatedIdentityRecord ? $this->toContract($record) : null;
  }

  public function findForUserProvider(string $userId, FederatedProvider $provider): ?FederatedConnection
  {
    $record = $this->entityManager->getRepository(FederatedIdentityRecord::class)->findOneBy([
      'userId' => $userId,
      'provider' => $provider->value,
    ]);

    return $record instanceof FederatedIdentityRecord ? $this->toContract($record) : null;
  }

  public function findForUser(string $userId): array
  {
    $records = $this->entityManager->getRepository(FederatedIdentityRecord::class)->findBy(
      ['userId' => $userId],
      ['connectedAt' => 'ASC'],
    );

    return array_map($this->toContract(...), $records);
  }

  public function save(FederatedConnection $connection): void
  {
    $record = $this->entityManager->find(FederatedIdentityRecord::class, $connection->id);
    if (!$record instanceof FederatedIdentityRecord) {
      $record = new FederatedIdentityRecord();
      $record->id = $connection->id;
    }

    $record->userId = $connection->userId;
    $record->provider = $connection->provider->value;
    $record->subject = $connection->subject;
    $record->email = $connection->email;
    $record->connectedAt = $connection->connectedAt;
    $record->lastUsedAt = $connection->lastUsedAt;
    $this->entityManager->persist($record);
    $this->entityManager->flush();
  }

  public function remove(FederatedConnection $connection): void
  {
    $record = $this->entityManager->find(FederatedIdentityRecord::class, $connection->id);
    if ($record instanceof FederatedIdentityRecord) {
      $this->entityManager->remove($record);
      $this->entityManager->flush();
    }
  }

  public function removePreservingAccess(FederatedProvider $provider, string $userId, bool $passwordConfigured): bool
  {
    return $this->entityManager->wrapInTransaction(function () use ($provider, $userId, $passwordConfigured): bool {
      /** @var list<FederatedIdentityRecord> $records */
      $records = $this->entityManager->createQueryBuilder()
        ->select('identity')
        ->from(FederatedIdentityRecord::class, 'identity')
        ->where('identity.userId = :userId')
        ->setParameter('userId', $userId)
        ->orderBy('identity.id', 'ASC')
        ->getQuery()
        ->setLockMode(LockMode::PESSIMISTIC_WRITE)
        ->getResult();

      $target = null;
      foreach ($records as $record) {
        if ($record->provider === $provider->value) {
          $target = $record;
        }
      }

      if (!$target instanceof FederatedIdentityRecord) {
        return true;
      }
      if (!$passwordConfigured && count($records) <= 1) {
        return false;
      }

      $this->entityManager->remove($target);
      $this->entityManager->flush();

      return true;
    });
  }

  private function toContract(FederatedIdentityRecord $record): FederatedConnection
  {
    return new FederatedConnection(
      id: $record->id,
      userId: $record->userId,
      provider: FederatedProvider::from($record->provider),
      subject: $record->subject,
      email: $record->email,
      connectedAt: $record->connectedAt,
      lastUsedAt: $record->lastUsedAt,
    );
  }
}
