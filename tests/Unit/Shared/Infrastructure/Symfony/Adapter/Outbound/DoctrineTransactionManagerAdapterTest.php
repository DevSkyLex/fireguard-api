<?php

declare(strict_types=1);

namespace Tests\Shared\Infrastructure\Symfony\Adapter\Outbound;

use Doctrine\DBAL\Exception as DoctrineDBALException;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Infrastructure\Symfony\Adapter\Outbound\DoctrineTransactionManagerAdapter;
use Shared\Infrastructure\Exception\TransactionExecutionException;

/**
 * Test DoctrineTransactionManagerAdapter
 * @final
 *
 * Test the DoctrineTransactionManagerAdapter class
 *
 * @category Unit Test
 * @package Tests\Unit\Shared\Infrastructure\Symfony\Adapter\Outbound
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class DoctrineTransactionManagerAdapterTest extends TestCase
{
  //#region Methods
  /**
   * Method testTransactionalReturnsOperationResult
   *
   * Test that the transactional method
   * returns the operation result
   *
   * @access public
   * @since 1.0.0
   *
   * @return void No return value
   */
  public function testTransactionalReturnsOperationResult(): void
  {
    $entityManager = $this->createMock(type: EntityManagerInterface::class);

    $entityManager->expects(self::once())
      ->method(constraint: 'wrapInTransaction')
      ->with(arguments: self::callback(callback: static fn($callback) => is_callable($callback)))
      ->willReturnCallback(callback: static function (callable $callback) use ($entityManager) {
        return $callback($entityManager);
      });

    $adapter = new DoctrineTransactionManagerAdapter(entityManager: $entityManager);

    $invocationCount = 0;
    $operation = static function () use (&$invocationCount) {
      ++$invocationCount;
      return 'result';
    };

    self::assertSame(
      expected: 'result',
      actual: $adapter->transactional($operation)
    );

    self::assertSame(
      expected: 1,
      actual: $invocationCount
    );
  }

  /**
   * Method testTransactionalWrapsExceptions
   *
   * Test that the transactional method
   * wraps exceptions
   *
   * @access public
   * @since 1.0.0
   *
   * @return void No return value
   *
   * @throws TransactionExecutionException When the transaction fails
   */
  public function testTransactionalWrapsExceptions(): void
  {
    $entityManager = $this->createMock(type: EntityManagerInterface::class);

    $entityManager->expects(self::once())
      ->method(constraint: 'wrapInTransaction')
      ->willThrowException(exception: new RuntimeException(message: 'failure'));

    $adapter = new DoctrineTransactionManagerAdapter(entityManager: $entityManager);

    $this->expectException(exception: TransactionExecutionException::class);

    $adapter->transactional(operation: static fn() => null);
  }

  /**
   * Method testTransactionalWrapsDoctrineDbalExceptions
   *
   * Test that the transactional method
   * wraps Doctrine DBAL exceptions
   *
   * @access public
   * @since 1.0.0
   *
   * @return void No return value
   *
   * @throws TransactionExecutionException When the transaction fails
   */
  public function testTransactionalWrapsDoctrineDbalExceptions(): void
  {
    $entityManager = $this->createMock(type: EntityManagerInterface::class);

    $entityManager->expects(self::once())
      ->method(constraint: 'wrapInTransaction')
      ->willThrowException(exception: $this->createMock(
        type: DoctrineDBALException::class
      ));

    $adapter = new DoctrineTransactionManagerAdapter(entityManager: $entityManager);

    $this->expectException(exception: TransactionExecutionException::class);

    $adapter->transactional(operation: static fn() => null);
  }
  //#endregion
}
