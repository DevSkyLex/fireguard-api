<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Symfony\Adapter\Outbound;

use Doctrine\DBAL\Exception as DoctrineDBALException;
use Doctrine\ORM\EntityManagerInterface;
use Shared\Application\Port\Outbound\TransactionManagerPort;
use Shared\Infrastructure\Exception\TransactionExecutionException;
use Throwable;

/**
 * Adapter DoctrineTransactionManagerAdapter.
 *
 * @category Outbound Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DoctrineTransactionManagerAdapter implements TransactionManagerPort
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initialize the Doctrine transaction
   * manager adapter.
   *
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager the entity manager implementation
   */
  public function __construct(
    private readonly EntityManagerInterface $entityManager,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method transactional
   * {@inheritdoc}
   *
   * Execute a transactional operation.
   *
   * @since 1.0.0
   *
   * @param callable $operation the operation to execute within a transaction
   *
   * @throws TransactionExecutionException if an exception occurs during the transaction
   *
   * @return mixed the result of the operation
   */
  public function transactional(callable $operation): mixed
  {
    try {
      return $this->entityManager->wrapInTransaction(
        static function (EntityManagerInterface $entityManager) use ($operation) {
          return $operation();
        },
      );
    } catch (Throwable $exception) {
      if ($exception instanceof DoctrineDBALException) {
        throw TransactionExecutionException::wrap(
          previous: $exception,
        );
      }

      throw $exception;
    }
  }
}
