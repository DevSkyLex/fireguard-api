<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Symfony\Adapter\Outbound;

use Doctrine\DBAL\Exception as DoctrineDBALException;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Infrastructure\Exception\TransactionExecutionException;
use Shared\Infrastructure\Symfony\Adapter\Outbound\DoctrineTransactionManagerAdapter;

#[CoversClass(className: DoctrineTransactionManagerAdapter::class)]
final class DoctrineTransactionManagerAdapterTest extends TestCase
{
  private EntityManagerInterface&MockObject $entityManager;

  private DoctrineTransactionManagerAdapter $adapter;

  protected function setUp(): void
  {
    $this->entityManager = $this->createMock(EntityManagerInterface::class);
    $this->adapter = new DoctrineTransactionManagerAdapter($this->entityManager);
  }

  #[Test]
  public function testTransactionalSuccess(): void
  {
    $operation = fn () => 'result';

    $this->entityManager->expects($this->once())
      ->method('wrapInTransaction')
      ->willReturnCallback(function (callable $callback) {
        return $callback($this->entityManager);
      });

    $result = $this->adapter->transactional($operation);

    $this->assertEquals('result', $result);
  }

  #[Test]
  public function testTransactionalRethrowsNonDbalExceptionUnchanged(): void
  {
    $operation = fn () => throw new Exception('Transaction failed');

    $this->entityManager->expects($this->once())
      ->method('wrapInTransaction')
      ->willReturnCallback(function (callable $callback) {
        return $callback($this->entityManager);
      });

    $this->expectException(Exception::class);
    $this->expectExceptionMessage('Transaction failed');
    $this->adapter->transactional($operation);
  }

  #[Test]
  public function testTransactionalWrapsDoctrineDbalException(): void
  {
    $exception = new DoctrineDBALException('dbal');

    $this->entityManager->expects($this->once())
      ->method('wrapInTransaction')
      ->willThrowException($exception);

    $this->expectException(TransactionExecutionException::class);
    $this->adapter->transactional(static fn () => 'unused');
  }
}
