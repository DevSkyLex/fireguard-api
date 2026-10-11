<?php

declare(strict_types=1);

namespace Tests\Unit\User\Infrastructure\Adapter\User;

use Authorization\Infrastructure\Adapter\Lifecycle\AuthorizationDataLifecycleAdapter;
use Doctrine\ORM\{EntityManagerInterface, Query, QueryBuilder};
use OAuth\Infrastructure\Adapter\Lifecycle\OAuthDataLifecycleAdapter;
use Otp\Application\Port\Inbound\Totp\TotpEnrollmentPurgePort;
use Otp\Infrastructure\Adapter\Lifecycle\OtpDataLifecycleAdapter;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Session\Infrastructure\Adapter\Lifecycle\SessionDataLifecycleAdapter;
use Shared\Application\Port\Outbound\FileCleanupPort;
use TrustedDevice\Infrastructure\Adapter\Lifecycle\TrustedDeviceDataLifecycleAdapter;
use User\Application\Port\Outbound\AvatarPersistencePort;
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

    $adapter = $this->purgeAdapter($entityManager, $this->createStub(TotpEnrollmentPurgePort::class));
    $adapter->purgeForUser('   ');
  }

  #[Test]
  public function testPurgeExecutesDeleteQueries(): void
  {
    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->expects(self::exactly(11))
      ->method('createQueryBuilder')
      ->willReturnCallback(fn (): QueryBuilder => $this->createQueryBuilderMock());

    $totpPurge = $this->createMock(TotpEnrollmentPurgePort::class);
    $totpPurge->expects(self::once())->method('purgeForUser')->with('user-123');
    $adapter = $this->purgeAdapter($entityManager, $totpPurge);
    $adapter->purgeForUser('user-123');
  }

  #[Test]
  public function testDeletionTransactionDelegatesToTheOwningEnrollmentLock(): void
  {
    $purge = $this->createMock(TotpEnrollmentPurgePort::class);
    $purge->expects(self::once())->method('withUserLock')->with('user-123', self::isCallable())->willReturnCallback(static fn (string $userId, callable $operation): mixed => $operation());
    $adapter = $this->purgeAdapter($this->createStub(EntityManagerInterface::class), $purge);
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
  private function purgeAdapter(EntityManagerInterface $manager, TotpEnrollmentPurgePort $totp): UserDataPurgeAdapter
  {
    return new UserDataPurgeAdapter(
      entityManager: $manager,
      totpEnrollmentPurge: $totp,
      sessionLifecycle: new SessionDataLifecycleAdapter($manager),
      otpLifecycle: new OtpDataLifecycleAdapter($manager),
      trusteddeviceLifecycle: new TrustedDeviceDataLifecycleAdapter($manager),
      oauthLifecycle: new OAuthDataLifecycleAdapter($manager),
      avatars: $this->createStub(AvatarPersistencePort::class),
      avatarCleanup: $this->createStub(FileCleanupPort::class),
      authorizationLifecycle: new AuthorizationDataLifecycleAdapter($manager),
    );
  }
}
