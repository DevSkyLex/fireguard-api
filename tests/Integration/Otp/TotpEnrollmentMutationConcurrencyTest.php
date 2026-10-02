<?php

declare(strict_types=1);

namespace Tests\Integration\Otp;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\ORM\{EntityManager, EntityManagerInterface};
use Otp\Application\Port\Outbound\Totp\TotpServicePort;
use Otp\Application\UseCase\Command\Totp\ConfirmTotp\{ConfirmTotpCommand, ConfirmTotpHandler};
use Otp\Application\UseCase\Command\Totp\SetupTotp\{SetupTotpCommand, SetupTotpHandler};
use Otp\Domain\Exception\TotpEnrollmentAlreadyActiveException;
use Otp\Domain\Model\Totp\TotpEnrollment;
use Otp\Domain\ValueObject\TotpSecret;
use Otp\Infrastructure\Adapter\Crypto\OpensslTotpSecretCipherAdapter;
use Otp\Infrastructure\Persistence\Doctrine\Mapper\TotpEnrollmentMapper;
use Otp\Infrastructure\Persistence\Doctrine\Repository\TotpEnrollmentRepository;
use PHPUnit\Framework\Attributes\Test;
use Shared\Application\Port\Outbound\EventDispatcherPort;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use User\Application\Port\Inbound\AccountStatusPort;

/**
 * Test TotpEnrollmentMutationConcurrencyTest.
 *
 * Interleaves independent PostgreSQL connections at the pending confirmation boundary.
 *
 * @category Integration Test
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class TotpEnrollmentMutationConcurrencyTest extends KernelTestCase
{
  // #region Methods
  #[Test]
  public function testConcurrentSetupWaitsForConfirmationAndThenRefusesTheFreshActiveFactor(): void
  {
    self::bootKernel();
    $configuredManager = static::getContainer()->get('doctrine.orm.auth_entity_manager');
    self::assertInstanceOf(EntityManagerInterface::class, $configuredManager);
    $url = $_ENV['AUTH_DATABASE_URL'] ?? $_SERVER['AUTH_DATABASE_URL'] ?? null;
    self::assertIsString($url);
    $confirmingConnection = DriverManager::getConnection(['url' => $url]);
    $setupConnection = DriverManager::getConnection(['url' => $url]);
    $confirmingManager = new EntityManager($confirmingConnection, $configuredManager->getConfiguration());
    $setupManager = new EntityManager($setupConnection, $configuredManager->getConfiguration());
    $setupConnection->executeStatement("SET lock_timeout = '150ms'");
    $mapper = new TotpEnrollmentMapper(new OpensslTotpSecretCipherAdapter([], ''));
    $confirmingRepository = new TotpEnrollmentRepository($confirmingManager, $mapper);
    $setupRepository = new TotpEnrollmentRepository($setupManager, $mapper);
    $userId = '44444444-4444-4444-8444-444444444444';
    $confirmingRepository->save(TotpEnrollment::startEnrollment($userId, new TotpSecret('BBBBBBBBBBBBBBBB'), 5));
    // A read performed before waiting must not be trusted after another connection activates the factor.
    self::assertFalse($setupRepository->findByUserId($userId)?->isActive());
    $setupService = $this->createMock(TotpServicePort::class);
    $setupService->expects(self::never())->method('generateSecret');
    $accountStatus = $this->createStub(AccountStatusPort::class);
    $accountStatus->method('isActive')->willReturn(true);
    $setupHandler = new SetupTotpHandler($setupService, $setupRepository, $accountStatus);
    $confirmingService = $this->createMock(TotpServicePort::class);
    $confirmingService->expects(self::once())->method('verify')->willReturnCallback(static function () use ($setupHandler, $userId): bool {
      try {
        $setupHandler->__invoke(new SetupTotpCommand($userId, 'test@example.com'));
        self::fail('Setup cannot decide enrollment state while confirmation holds the user lock.');
      } catch (DriverException $exception) {
        self::assertSame('55P03', $exception->getSQLState());
      }

      return true;
    });
    $confirmingHandler = new ConfirmTotpHandler($confirmingRepository, $confirmingService, $this->createStub(EventDispatcherPort::class));

    try {
      self::assertTrue($confirmingHandler->__invoke(new ConfirmTotpCommand($userId, '123456'))->success);

      try {
        $setupHandler->__invoke(new SetupTotpCommand($userId, 'test@example.com'));
        self::fail('A retry must read the freshly active factor and refuse unproved replacement.');
      } catch (TotpEnrollmentAlreadyActiveException) {
        $active = $confirmingRepository->findByUserId($userId);
        self::assertNotNull($active);
        self::assertTrue($active->isActive());
        self::assertFalse($active->hasPending());
      }
    } finally {
      $confirmingConnection->executeStatement('DELETE FROM totp_enrollments WHERE user_id = ?', [$userId]);
      $confirmingManager->close();
      $setupManager->close();
      $confirmingConnection->close();
      $setupConnection->close();
    }
  }
  // #endregion
}
