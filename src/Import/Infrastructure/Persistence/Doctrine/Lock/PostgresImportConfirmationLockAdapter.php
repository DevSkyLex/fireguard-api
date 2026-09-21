<?php

declare(strict_types=1);

namespace Import\Infrastructure\Persistence\Doctrine\Lock;

use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Import\Application\Port\Outbound\ImportConfirmationLockPort;
use Import\Infrastructure\Persistence\Doctrine\Record\ImportJobRecord;

/**
 * Adapter PostgresImportConfirmationLockAdapter.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class PostgresImportConfirmationLockAdapter implements ImportConfirmationLockPort
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager main entity manager
   */
  public function __construct(private EntityManagerInterface $entityManager)
  {
  }

  public function synchronized(string $simulationId, callable $operation): mixed
  {
    return $this->entityManager->wrapInTransaction(function () use ($simulationId, $operation): mixed {
      $record = $this->entityManager->find(ImportJobRecord::class, $simulationId, LockMode::PESSIMISTIC_WRITE);
      if (null !== $record) {
        $this->entityManager->refresh($record, LockMode::PESSIMISTIC_WRITE);
      }

      return $operation();
    });
  }
}
