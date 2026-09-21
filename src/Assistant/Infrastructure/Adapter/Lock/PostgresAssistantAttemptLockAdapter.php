<?php

declare(strict_types=1);

namespace Assistant\Infrastructure\Adapter\Lock;

use Assistant\Application\Port\Outbound\AssistantAttemptLockPort;
use Assistant\Infrastructure\Persistence\Doctrine\Record\AssistantMessageRecord;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

/** Adapter PostgresAssistantAttemptLockAdapter. Refreshes long-lived workers under the same row lock as HTTP commands. */
final readonly class PostgresAssistantAttemptLockAdapter implements AssistantAttemptLockPort
{
  public function __construct(private EntityManagerInterface $entityManager)
  {
  }

  public function synchronized(string $messageId, callable $operation): mixed
  {
    return $this->entityManager->wrapInTransaction(function () use ($messageId, $operation): mixed {
      $record = $this->entityManager->find(AssistantMessageRecord::class, $messageId, LockMode::PESSIMISTIC_WRITE);
      if (null !== $record) {
        $this->entityManager->refresh($record, LockMode::PESSIMISTIC_WRITE);
      }

      return $operation();
    });
  }
}
