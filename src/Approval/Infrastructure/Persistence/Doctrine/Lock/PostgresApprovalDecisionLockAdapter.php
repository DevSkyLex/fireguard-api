<?php

declare(strict_types=1);

namespace Approval\Infrastructure\Persistence\Doctrine\Lock;

use Approval\Application\Port\Outbound\ApprovalDecisionLockPort;
use Approval\Infrastructure\Persistence\Doctrine\Record\ApprovalRequestRecord;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Adapter PostgresApprovalDecisionLockAdapter.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class PostgresApprovalDecisionLockAdapter implements ApprovalDecisionLockPort
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

  public function synchronized(string $requestId, callable $decision): mixed
  {
    return $this->entityManager->wrapInTransaction(function () use ($requestId, $decision): mixed {
      $record = $this->entityManager->find(ApprovalRequestRecord::class, $requestId, LockMode::PESSIMISTIC_WRITE);
      if (null !== $record) {
        $this->entityManager->refresh($record, LockMode::PESSIMISTIC_WRITE);
      }

      return $decision();
    });
  }
}
