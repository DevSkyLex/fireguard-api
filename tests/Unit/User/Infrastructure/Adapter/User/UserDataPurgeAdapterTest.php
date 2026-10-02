<?php

declare(strict_types=1);

namespace Tests\Unit\User\Infrastructure\Adapter\User;

use Doctrine\ORM\{EntityManagerInterface, Query, QueryBuilder};
use Otp\Application\Port\Inbound\Totp\TotpEnrollmentPurgePort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use User\Infrastructure\Adapter\User\UserDataPurgeAdapter;

/**
 * Test UserDataPurgeAdapterTest.
 *
 * @category Adapter Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(UserDataPurgeAdapter::class)]
final class UserDataPurgeAdapterTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testPurgeSkipsWhenUserIdBlank(): void
  {
    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->expects(self::never())
      ->method('createQueryBuilder');

    $adapter = new UserDataPurgeAdapter(entityManager: $entityManager, totpEnrollmentPurge: $this->createStub(TotpEnrollmentPurgePort::class));
    $adapter->purgeForUser('   ');
  }

  #[Test]
  public function testPurgeExecutesDeleteQueries(): void
  {
    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->expects(self::exactly(10))
      ->method('createQueryBuilder')
      ->willReturnCallback(fn (): QueryBuilder => $this->createQueryBuilderMock());

    $totpPurge = $this->createMock(TotpEnrollmentPurgePort::class);
    $totpPurge->expects(self::once())->method('purgeForUser')->with('user-123');
    $adapter = new UserDataPurgeAdapter(entityManager: $entityManager, totpEnrollmentPurge: $totpPurge);
    $adapter->purgeForUser('user-123');
  }

  #[Test]
  public function testDeletionTransactionDelegatesToTheOwningEnrollmentLock(): void
  {
    $purge = $this->createMock(TotpEnrollmentPurgePort::class);
    $purge->expects(self::once())->method('withUserLock')->with('user-123', self::isCallable())->willReturnCallback(static fn (string $userId, callable $operation): mixed => $operation());
    $adapter = new UserDataPurgeAdapter($this->createStub(EntityManagerInterface::class), $purge);
    self::assertSame('committed-result', $adapter->withUserLock('user-123', static fn (): string => 'committed-result'));
  }

  private function createQueryBuilderMock(): QueryBuilder
  {
    $query = $this->createStub(Query::class);
    $query->method('execute')
      ->willReturn(1);

    $builder = $this->createStub(QueryBuilder::class);
    $builder->method('select')->willReturnSelf();
    $builder->method('from')->willReturnSelf();
    $builder->method('where')->willReturnSelf();
    $builder->method('delete')->willReturnSelf();
    $builder->method('setParameter')->willReturnSelf();
    $builder->method('getDQL')->willReturn('SELECT a.identifier FROM tokens');
    $builder->method('getQuery')->willReturn($query);

    return $builder;
  }
  // #endregion
}
