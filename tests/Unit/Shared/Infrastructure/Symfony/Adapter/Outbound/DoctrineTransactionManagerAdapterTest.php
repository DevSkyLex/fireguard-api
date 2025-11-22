<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Symfony\Adapter\Outbound;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Shared\Infrastructure\Exception\TransactionExecutionException;
use Shared\Infrastructure\Symfony\Adapter\Outbound\DoctrineTransactionManagerAdapter;
use Exception;

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
    $operation = fn() => 'result';

    $this->entityManager->expects($this->once())
      ->method('wrapInTransaction')
      ->willReturnCallback(function ($callback) {
        return $callback($this->entityManager);
      });

    $result = $this->adapter->transactional($operation);

    $this->assertEquals('result', $result);
  }

  #[Test]
  public function testTransactionalThrowsException(): void
  {
    $operation = fn() => throw new Exception('Transaction failed');

    $this->entityManager->expects($this->once())
      ->method('wrapInTransaction')
      ->willReturnCallback(function ($callback) {
        return $callback($this->entityManager);
      });

    $this->expectException(TransactionExecutionException::class);
    $this->adapter->transactional($operation);
  }
}

