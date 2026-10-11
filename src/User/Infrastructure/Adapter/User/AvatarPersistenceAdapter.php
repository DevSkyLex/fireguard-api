<?php

declare(strict_types=1);

namespace User\Infrastructure\Adapter\User;

use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Otp\Application\Port\Inbound\Totp\TotpEnrollmentPurgePort;
use User\Application\Port\Outbound\AvatarPersistencePort;
use User\Domain\Exception\UserNotFoundException;
use User\Infrastructure\Persistence\Doctrine\Record\UserRecord;

use function is_string;

/** Serializes uploads with account deletion and refreshes the winning profile.
 * @category Adapter
 */
final readonly class AvatarPersistenceAdapter implements AvatarPersistencePort
{
  public function __construct(private EntityManagerInterface $entityManager, private TotpEnrollmentPurgePort $userLocks)
  {
  }

  public function generation(string $userId): ?string
  {
    $value = $this->entityManager->getConnection()->fetchOne('SELECT avatar_storage_key FROM users WHERE id = ?', [$userId]);

    return is_string($value) ? $value : null;
  }

  public function replace(string $userId, string $generation): void
  {
    if (!$this->entityManager->getConnection()->isTransactionActive()) {
      throw new LogicException('Avatar pointers require the owning auth transaction.');
    }
    $record = $this->entityManager->find(UserRecord::class, $userId);
    if (null === $record) {
      throw UserNotFoundException::withId($userId);
    }
    $record->avatarStorageKey = $generation;
    $this->entityManager->flush();
  }

  public function withUserLock(string $userId, callable $operation): mixed
  {
    if (!$this->entityManager->getConnection()->isTransactionActive()) {
      throw new LogicException('Avatar mutations require the owning auth transaction.');
    }

    return $this->userLocks->withUserLock($userId, function () use ($userId, $operation): mixed {
      $record = $this->entityManager->find(UserRecord::class, $userId);
      if (null === $record) {
        throw UserNotFoundException::withId($userId);
      }
      $this->entityManager->refresh($record, LockMode::PESSIMISTIC_WRITE);

      return $operation();
    });
  }
}
