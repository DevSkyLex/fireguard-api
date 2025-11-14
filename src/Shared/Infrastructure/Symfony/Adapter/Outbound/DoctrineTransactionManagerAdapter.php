<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Symfony\Adapter\Outbound;

use Doctrine\DBAL\Exception as DoctrineDBALException;
use Doctrine\ORM\EntityManagerInterface;
use Shared\Application\Port\Outbound\TransactionManagerPort;
use Shared\Infrastructure\Symfony\Exception\TransactionExecutionException;
use Throwable;

/**
 * Adapter DoctrineTransactionManagerAdapter
 * @implements TransactionManagerPort
 * @final
 *
 * Adapter wrapping Doctrine's transactional capabilities
 * exposed through the transaction manager port.
 *
 * @category Outbound Adapter
 * @package Shared\Infrastructure\Symfony\Adapter\Outbound
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DoctrineTransactionManagerAdapter implements TransactionManagerPort
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initialize the Doctrine transaction
   * manager adapter.
   *
   * @access public
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager The entity manager implementation.
   */
  public function __construct(
    private readonly EntityManagerInterface $entityManager
  ) {}
  //#endregion

  //#region Methods
  /**
   * Method transactional
   * @method transactional(callable $operation): mixed
   * {@inheritdoc}
   *
   * Execute a transactional operation.
   *
   * @access public
   * @since 1.0.0
   *
   * @param callable $operation The operation to execute within a transaction.
   *
   * @return mixed The result of the operation.
   *
   * @throws TransactionExecutionException If an exception occurs during the transaction.
   */
  public function transactional(callable $operation): mixed
  {
    try {
      return $this->entityManager->wrapInTransaction(
        static function (EntityManagerInterface $entityManager) use ($operation) {
          return $operation();
        }
      );
    }
    catch (Throwable $exception) {
      if ($exception instanceof DoctrineDBALException) {
        throw TransactionExecutionException::wrap(
          previous: $exception,
        );
      }

      throw TransactionExecutionException::wrap(
        previous: $exception,
      );
    }
  }
}
