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
  // #region Constructor
  /**
   * Method __construct
   *
   * Uses the main entity manager to serialize assistant attempt work with a database row lock.
   *
   * @access public
   *
   * @param EntityManagerInterface $entityManager the main entity manager
   *
   * @return void
   */
  public function __construct(private EntityManagerInterface $entityManager)
  {
  }

  // #endregion

  // #region Methods
  /**
   * Method synchronized.
   *
   * Runs the assistant attempt operation while holding a pessimistic message row lock.
   *
   * @access public
   *
   * @template T
   *
   * @param string $messageId the message identifier to lock
   * @param callable(): T $operation the attempt operation to run under the lock
   *
   * @return T the attempt operation result
   */
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
  // #endregion
}
