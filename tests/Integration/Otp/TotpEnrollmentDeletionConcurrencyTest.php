<?php

declare(strict_types=1);

namespace Tests\Integration\Otp;

use DateTimeImmutable;
use Doctrine\DBAL\{Connection, DriverManager};
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\ORM\{EntityManager, EntityManagerInterface};
use Otp\Application\Port\Inbound\Totp\TotpEnrollmentPurgePort;
use Otp\Application\Port\Outbound\Totp\TotpServicePort;
use Otp\Application\UseCase\Command\Totp\SetupTotp\{SetupTotpCommand, SetupTotpHandler};
use Otp\Domain\Exception\TotpEnrollmentUnavailableException;
use Otp\Domain\ValueObject\TotpSecret;
use Otp\Infrastructure\Adapter\Crypto\OpensslTotpSecretCipherAdapter;
use Otp\Infrastructure\Persistence\Doctrine\Mapper\TotpEnrollmentMapper;
use Otp\Infrastructure\Persistence\Doctrine\Repository\TotpEnrollmentRepository;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use User\Application\UseCase\Command\User\DeleteUser\{DeleteUserCommand, DeleteUserHandler};
use User\Infrastructure\Adapter\User\{AccountStatusAdapter, UserDataPurgeAdapter};
use User\Infrastructure\Persistence\Doctrine\Mapper\UserMapper;
use User\Infrastructure\Persistence\Doctrine\Record\UserRecord;
use User\Infrastructure\Persistence\Doctrine\Repository\UserRepository;

/**
 * Test TotpEnrollmentDeletionConcurrencyTest.
 *
 * Interleaves real account deletion and enrollment on independent auth connections.
 *
 * @category Integration Test
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class TotpEnrollmentDeletionConcurrencyTest extends KernelTestCase
{
  // #region Properties
  /**
   * Property setupManager.
   *
   * Stores the independent enrollment entity manager.
   */
  private EntityManagerInterface $setupManager;

  /**
   * Property deletionManager.
   *
   * Stores the independent account-deletion entity manager.
   */
  private EntityManagerInterface $deletionManager;

  /**
   * Property setupConnection.
   *
   * Stores the independent enrollment auth connection.
   */
  private Connection $setupConnection;

  /**
   * Property deletionConnection.
   *
   * Stores the independent deletion auth connection.
   */
  private Connection $deletionConnection;

  /**
   * Property setupRepository.
   *
   * Stores the real enrollment persistence and lock boundary.
   */
  private TotpEnrollmentRepository $setupRepository;

  /**
   * Property deletionHandler.
   *
   * Stores the real auth-only account deletion use case.
   */
  private DeleteUserHandler $deletionHandler;

  /**
   * Property userId.
   *
   * Stores the isolated synthetic account identifier.
   */
  private string $userId = '44444444-4444-4444-8444-444444444445';
  // #endregion

  // #region Setup
  /**
   * Method setUp.
   *
   * Creates independent auth connections and a synthetic active account.
   *
   * @access protected
   *
   * @return void
   */
  protected function setUp(): void
  {
    self::bootKernel();
    $configuredManager = static::getContainer()->get('doctrine.orm.auth_entity_manager');
    self::assertInstanceOf(EntityManagerInterface::class, $configuredManager);
    $url = $_ENV['AUTH_DATABASE_URL'] ?? $_SERVER['AUTH_DATABASE_URL'] ?? null;
    self::assertIsString($url);
    $this->setupConnection = DriverManager::getConnection(['url' => $url]);
    $this->deletionConnection = DriverManager::getConnection(['url' => $url]);
    $this->deletionConnection->executeStatement("SET lock_timeout = '150ms'");
    $this->setupManager = new EntityManager($this->setupConnection, $configuredManager->getConfiguration());
    $this->deletionManager = new EntityManager($this->deletionConnection, $configuredManager->getConfiguration());
    $mapper = new TotpEnrollmentMapper(new OpensslTotpSecretCipherAdapter([], ''));
    $this->setupRepository = new TotpEnrollmentRepository($this->setupManager, $mapper);
    $deletionRepository = new TotpEnrollmentRepository($this->deletionManager, $mapper);
    $this->deletionHandler = new DeleteUserHandler(
      new UserRepository($this->deletionManager, new UserMapper()),
      new UserDataPurgeAdapter($this->deletionManager, $deletionRepository),
    );
    $record = new UserRecord();
    $record->id = $this->userId;
    $record->username = 'totp-deletion-race';
    $record->email = 'totp-deletion-race@example.test';
    $record->firstName = 'Test';
    $record->lastName = 'Account';
    $record->status = 'active';
    $record->emailVerified = true;
    $record->createdAt = new DateTimeImmutable();
    $this->setupManager->persist($record);
    $this->setupManager->flush();
  }

  /**
   * Method tearDown.
   *
   * Removes only the synthetic fixture and closes its independent connections.
   *
   * @access protected
   *
   * @return void
   */
  protected function tearDown(): void
  {
    $this->setupConnection->executeStatement('DELETE FROM totp_enrollments WHERE user_id = ?', [$this->userId]);
    $this->setupConnection->executeStatement('DELETE FROM users WHERE id = ?', [$this->userId]);
    $this->setupManager->close();
    $this->deletionManager->close();
    $this->setupConnection->close();
    $this->deletionConnection->close();
    parent::tearDown();
  }
  // #endregion

  // #region Methods
  /**
   * Method testDeletionWaitsBeforeRemovingTheUserWhileSetupHoldsItsLock.
   *
   * Proves deletion takes the enrollment lock before any account removal and purges a committed setup.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testDeletionWaitsBeforeRemovingTheUserWhileSetupHoldsItsLock(): void
  {
    $service = $this->createMock(TotpServicePort::class);
    $service->expects(self::once())->method('generateSecret')->willReturnCallback(function (): TotpSecret {
      try {
        $this->deletionHandler->__invoke(new DeleteUserCommand($this->userId));
        self::fail('Deletion must wait for the enrollment transaction before writing account data.');
      } catch (DriverException $exception) {
        self::assertSame('55P03', $exception->getSQLState());
      }
      self::assertSame('active', $this->setupConnection->fetchOne('SELECT status FROM users WHERE id = ?', [$this->userId]));

      return new TotpSecret('BBBBBBBBBBBBBBBB');
    });
    $service->method('getProvisioningUri')->willReturn('otpauth://totp/test');
    $setup = new SetupTotpHandler($service, $this->setupRepository, new AccountStatusAdapter($this->setupManager));
    $setup->__invoke(new SetupTotpCommand($this->userId, 'test@example.test'));
    self::assertNotNull($this->setupRepository->findByUserId($this->userId));

    $this->deletionHandler->__invoke(new DeleteUserCommand($this->userId));
    self::assertFalse($this->setupConnection->fetchOne('SELECT id FROM users WHERE id = ?', [$this->userId]));
    self::assertFalse($this->setupConnection->fetchOne('SELECT user_id FROM totp_enrollments WHERE user_id = ?', [$this->userId]));
  }

  /**
   * Method testAlreadyAuthenticatedSetupCannotRecreateSecretsAfterDeletionCommits.
   *
   * Refuses a suspended authenticated command after deletion despite a preloaded user entity.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testAlreadyAuthenticatedSetupCannotRecreateSecretsAfterDeletionCommits(): void
  {
    $command = new SetupTotpCommand($this->userId, 'test@example.test');
    self::assertInstanceOf(UserRecord::class, $this->setupManager->find(UserRecord::class, $this->userId));
    $this->deletionHandler->__invoke(new DeleteUserCommand($this->userId));
    self::assertFalse($this->setupConnection->fetchOne('SELECT id FROM users WHERE id = ?', [$this->userId]));
    $service = $this->createMock(TotpServicePort::class);
    $service->expects(self::never())->method('generateSecret');
    $service->method('getProvisioningUri')->willReturn('otpauth://totp/test');
    $setup = new SetupTotpHandler($service, $this->setupRepository, new AccountStatusAdapter($this->setupManager));

    try {
      $setup->__invoke($command);
      self::fail('A principal authenticated before deletion must not recreate its authenticator secrets.');
    } catch (TotpEnrollmentUnavailableException) {
      self::assertFalse($this->setupConnection->fetchOne('SELECT user_id FROM totp_enrollments WHERE user_id = ?', [$this->userId]));
    }
  }

  /**
   * Method testSetupWaitsForDeletionAndThenRefusesTheFreshlyMissingAccount.
   *
   * Proves setup waits through account deletion and then reads the missing account freshly.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testSetupWaitsForDeletionAndThenRefusesTheFreshlyMissingAccount(): void
  {
    $this->setupConnection->executeStatement("SET lock_timeout = '150ms'");
    $service = $this->createMock(TotpServicePort::class);
    $service->expects(self::never())->method('generateSecret');
    $setup = new SetupTotpHandler($service, $this->setupRepository, new AccountStatusAdapter($this->setupManager));
    $repository = new TotpEnrollmentRepository($this->deletionManager, new TotpEnrollmentMapper(new OpensslTotpSecretCipherAdapter([], '')));
    $purge = $this->createMock(TotpEnrollmentPurgePort::class);
    $purge->method('withUserLock')->willReturnCallback(static fn (string $userId, callable $operation): mixed => $repository->withUserLock($userId, $operation));
    $purge->expects(self::once())->method('purgeForUser')->willReturnCallback(function (string $userId) use ($repository, $setup): void {
      $repository->purgeForUser($userId);

      try {
        $setup->__invoke(new SetupTotpCommand($userId, 'test@example.test'));
        self::fail('Setup must wait until account removal and authenticator purge commit.');
      } catch (DriverException $exception) {
        self::assertSame('55P03', $exception->getSQLState());
      }
    });
    $delete = new DeleteUserHandler(
      new UserRepository($this->deletionManager, new UserMapper()),
      new UserDataPurgeAdapter($this->deletionManager, $purge),
    );
    $delete->__invoke(new DeleteUserCommand($this->userId));

    try {
      $setup->__invoke(new SetupTotpCommand($this->userId, 'test@example.test'));
      self::fail('Setup must refuse the account removed while it was waiting.');
    } catch (TotpEnrollmentUnavailableException) {
      self::assertFalse($this->setupConnection->fetchOne('SELECT user_id FROM totp_enrollments WHERE user_id = ?', [$this->userId]));
    }
  }

  /**
   * Method testPurgeFailureRollsBackAccountRemovalAndSecretDeletion.
   *
   * Proves a cleanup failure rolls back both account removal and secret deletion.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testPurgeFailureRollsBackAccountRemovalAndSecretDeletion(): void
  {
    $service = $this->createStub(TotpServicePort::class);
    $service->method('generateSecret')->willReturn(new TotpSecret('BBBBBBBBBBBBBBBB'));
    $service->method('getProvisioningUri')->willReturn('otpauth://totp/test');
    $setup = new SetupTotpHandler($service, $this->setupRepository, new AccountStatusAdapter($this->setupManager));
    $setup->__invoke(new SetupTotpCommand($this->userId, 'test@example.test'));
    $repository = new TotpEnrollmentRepository($this->deletionManager, new TotpEnrollmentMapper(new OpensslTotpSecretCipherAdapter([], '')));
    $purge = $this->createMock(TotpEnrollmentPurgePort::class);
    $purge->method('withUserLock')->willReturnCallback(static fn (string $userId, callable $operation): mixed => $repository->withUserLock($userId, $operation));
    $purge->expects(self::once())->method('purgeForUser')->willReturnCallback(static function (string $userId) use ($repository): void {
      $repository->purgeForUser($userId);

      throw new RuntimeException('Synthetic cleanup failure.');
    });
    $delete = new DeleteUserHandler(
      new UserRepository($this->deletionManager, new UserMapper()),
      new UserDataPurgeAdapter($this->deletionManager, $purge),
    );

    try {
      $delete->__invoke(new DeleteUserCommand($this->userId));
      self::fail('Cleanup failures must reach the caller.');
    } catch (RuntimeException $exception) {
      self::assertSame('Synthetic cleanup failure.', $exception->getMessage());
      self::assertSame('active', $this->setupConnection->fetchOne('SELECT status FROM users WHERE id = ?', [$this->userId]));
      self::assertSame($this->userId, $this->setupConnection->fetchOne('SELECT user_id FROM totp_enrollments WHERE user_id = ?', [$this->userId]));
    }
  }
  // #endregion
}
