<?php

declare(strict_types=1);

namespace Intervention\Infrastructure\Adapter\Workflow;

use Doctrine\ORM\EntityManagerInterface;
use Shared\Application\Port\Outbound\TransactionManagerPort;
use Throwable;

/**
 * Class InterventionTransactionManagerAdapter
 *
 * Owns a main transaction and clears rolled-back ORM state before the next occurrence.
 *
 * @category Adapter
 */
final readonly class InterventionTransactionManagerAdapter implements TransactionManagerPort
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param EntityManagerInterface $entityManager explicitly wired main entity manager
   *
   * @return void
   */
  public function __construct(private EntityManagerInterface $entityManager)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method transactional
   *
   * Uses DBAL savepoints without closing a healthy manager for validation failures.
   *
   * @access public
   *
   * @template T
   *
   * @param callable(): T $operation complete local write operation
   *
   * @return T the transaction result
   */
  public function transactional(callable $operation): mixed
  {
    try {
      return $this->entityManager->getConnection()->transactional(static fn () => $operation());
    } catch (Throwable $exception) {
      if ($this->entityManager->isOpen()) {
        $this->entityManager->clear();
      }

      throw $exception;
    }
  }
  // #endregion
}
