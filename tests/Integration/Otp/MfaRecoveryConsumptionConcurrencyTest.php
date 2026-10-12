<?php

declare(strict_types=1);

namespace Tests\Integration\Otp;

use DateTimeImmutable;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\ORM\{EntityManager, EntityManagerInterface};
use Doctrine\Persistence\ConnectionRegistry;
use Otp\Application\Contract\Challenge\OtpPurpose as Purpose;
use Otp\Application\Contract\Event\Totp\RecoveryCodeConsumedEvent;
use Otp\Application\Port\Outbound\Internal\Challenge\OtpRepositoryPort;
use Otp\Application\Port\Outbound\Internal\Totp\TotpServicePort;
use Otp\Application\UseCase\Command\Challenge\VerifyMfaChallenge\{VerifyMfaChallengeCommand, VerifyMfaChallengeHandler};
use Otp\Domain\Model\Otp;
use Otp\Domain\Model\Totp\TotpEnrollment;
use Otp\Domain\ValueObject\{OtpChannel, OtpId, OtpPurpose, RecoveryCode, TotpSecret};
use Otp\Infrastructure\Adapter\Crypto\OpensslTotpSecretCipherAdapter;
use Otp\Infrastructure\Persistence\Doctrine\Mapper\{OtpMapper, TotpEnrollmentMapper};
use Otp\Infrastructure\Persistence\Doctrine\Repository\{OtpRepository, TotpEnrollmentRepository};
use RuntimeException;
use Shared\Application\Port\Outbound\{CurrentActorPort, UuidGeneratorPort};
use Shared\Infrastructure\Messaging\Outbox\{OutboxEvent, TransactionalEventDispatcher};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\{DoctrineTransport, DoctrineTransportFactory};
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use User\Application\Port\Inbound\SignInEligibilityPort;

use function iterator_to_array;
use function serialize;

/**
 * Class MfaRecoveryConsumptionConcurrencyTest
 *
 * Interleaves two real PostgreSQL connections and verifies a stale enrollment cannot authorize reuse.
 *
 * @category Integration Test
 */
final class MfaRecoveryConsumptionConcurrencyTest extends KernelTestCase
{
  public function testConcurrentClaimsSerializeAndSecondChallengeCannotReuseCommittedCode(): void
  {
    self::bootKernel();
    $configured = static::getContainer()->get('doctrine.orm.auth_entity_manager');
    self::assertInstanceOf(EntityManagerInterface::class, $configured);
    $url = $_ENV['AUTH_DATABASE_URL'] ?? $_SERVER['AUTH_DATABASE_URL'] ?? null;
    self::assertIsString($url);
    $firstConnection = DriverManager::getConnection(['url' => $url]);
    $secondConnection = DriverManager::getConnection(['url' => $url]);
    $firstManager = new EntityManager($firstConnection, $configured->getConfiguration());
    $secondManager = new EntityManager($secondConnection, $configured->getConfiguration());
    $secondConnection->executeStatement("SET lock_timeout = '150ms'");
    $mapper = new TotpEnrollmentMapper(new OpensslTotpSecretCipherAdapter([], ''));
    $firstEnrollments = new TotpEnrollmentRepository($firstManager, $mapper);
    $secondEnrollments = new TotpEnrollmentRepository($secondManager, $mapper);
    $firstChallenges = new OtpRepository($firstManager, new OtpMapper());
    $secondChallenges = new OtpRepository($secondManager, new OtpMapper());
    $userId = '44444444-4444-4444-8444-444444444446';
    $code = 'abcd-abcd-abcd-abcd-abcd-abcd-abcd-abcd';
    $hash = RecoveryCode::hash($code);
    self::assertIsString($hash);
    $enrollment = TotpEnrollment::startEnrollment($userId, new TotpSecret('JBSWY3DPEHPK3PXP'), 5);
    $enrollment->confirmPending(true);
    $enrollment->replaceRecoveryCodes([$hash], new DateTimeImmutable());
    $firstEnrollments->save($enrollment);
    $firstChallenge = Otp::generate(new OtpId('11111111-1111-4111-8111-111111111118'), $userId, OtpPurpose::LOGIN, OtpChannel::TOTP, 'test@example.test');
    $secondChallenge = Otp::generate(new OtpId('11111111-1111-4111-8111-111111111119'), $userId, OtpPurpose::LOGIN, OtpChannel::TOTP, 'test@example.test');
    $foreignOwner = '44444444-4444-4444-8444-444444444447';
    $foreignChallenge = Otp::generate(new OtpId('11111111-1111-4111-8111-111111111120'), $foreignOwner, OtpPurpose::LOGIN, OtpChannel::TOTP, 'other@example.test');
    $firstChallenges->save($firstChallenge);
    $firstChallenges->save($secondChallenge);
    $firstChallenges->save($foreignChallenge);
    self::assertSame(1, $secondEnrollments->findByUserId($userId)?->recoveryCodesRemaining());
    $accounts = $this->createStub(SignInEligibilityPort::class);
    $accounts->method('isEligible')->willReturn(true);
    $registry = $this->createStub(ConnectionRegistry::class);
    $registry->method('getConnection')->willReturn($firstConnection);
    $transport = new DoctrineTransportFactory($registry)->createTransport('doctrine://auth?queue_name=mfa_recovery_regression&auto_setup=false', ['use_notify' => false], new PhpSerializer());
    self::assertInstanceOf(DoctrineTransport::class, $transport);
    $transport->setup();
    $firstConnection->executeStatement('DELETE FROM messenger_messages WHERE queue_name = ?', ['mfa_recovery_regression']);
    $ids = $this->createStub(UuidGeneratorPort::class);
    $ids->method('generate')->willReturn('bc000000-0000-4000-8000-000000000201');
    $events = new TransactionalEventDispatcher($firstConnection, $transport, $ids, $this->createStub(CurrentActorPort::class));
    $firstHandler = new VerifyMfaChallengeHandler($firstEnrollments, $firstChallenges, $this->createStub(TotpServicePort::class), $accounts, $events, policy: $this->createStub(\Auth\Application\Port\Outbound\AuthenticationPolicyPort::class));
    $secondHandler = new VerifyMfaChallengeHandler($secondEnrollments, $secondChallenges, $this->createStub(TotpServicePort::class), $accounts, events: $this->createStub(\Shared\Application\Port\Outbound\EventDispatcherPort::class), policy: $this->createStub(\Auth\Application\Port\Outbound\AuthenticationPolicyPort::class));
    $first = new VerifyMfaChallengeCommand($firstChallenge->challengeToken()->value, $userId, Purpose::LOGIN, 'recovery_code', $code);
    $second = new VerifyMfaChallengeCommand($secondChallenge->challengeToken()->value, $userId, Purpose::LOGIN, 'recovery_code', $code);

    try {
      $firstConnection->transactional(function () use ($firstChallenges, $foreignChallenge, $secondHandler, $userId, $code): void {
        self::assertNotNull($firstChallenges->findByChallengeToken($foreignChallenge->challengeToken()));
        $wrongOwner = new VerifyMfaChallengeCommand($foreignChallenge->challengeToken()->value, $userId, Purpose::LOGIN, 'recovery_code', $code);
        self::assertSame('invalid_token', $secondHandler($wrongOwner)->verification->errorCode);
      });
      self::assertSame(0, $firstChallenges->findByChallengeToken($foreignChallenge->challengeToken())?->attempts());
      $failedRepository = $this->createMock(OtpRepositoryPort::class);
      $failedRepository->method('findForVerification')->willReturnCallback($firstChallenges->findForVerification(...));
      $failedRepository->expects(self::once())->method('save')->willThrowException(new RuntimeException('Interrupted before challenge persistence'));
      $failedHandler = new VerifyMfaChallengeHandler($firstEnrollments, $failedRepository, $this->createStub(TotpServicePort::class), $accounts, $events, policy: $this->createStub(\Auth\Application\Port\Outbound\AuthenticationPolicyPort::class));

      try {
        $failedHandler($first);
        self::fail('Interrupted challenge persistence must fail the whole recovery transaction.');
      } catch (RuntimeException $failure) {
        self::assertSame('Interrupted before challenge persistence', $failure->getMessage());
      }
      self::assertSame(1, $firstEnrollments->findByUserId($userId)?->recoveryCodesRemaining());
      self::assertSame(0, $secondConnection->fetchOne('SELECT COUNT(*) FROM messenger_messages WHERE queue_name = ?', ['mfa_recovery_regression']));
      $firstEnrollments->withUserLock($userId, function () use ($firstHandler, $first, $secondHandler, $second, $secondConnection): void {
        self::assertTrue($firstHandler($first)->verification->success);
        self::assertSame(0, $secondConnection->fetchOne('SELECT COUNT(*) FROM messenger_messages WHERE queue_name = ?', ['mfa_recovery_regression']));

        try {
          $secondHandler($second);
          self::fail('A parallel recovery claim must wait for the enrollment owner lock.');
        } catch (DriverException $exception) {
          self::assertSame('55P03', $exception->getSQLState());
        }
      });
      $retry = $secondHandler($second);
      self::assertFalse($retry->verification->success);
      self::assertSame('invalid_code', $retry->verification->errorCode);
      $stored = $secondEnrollments->findByUserId($userId);
      self::assertSame(0, $stored->recoveryCodesRemaining());
      self::assertTrue($secondChallenges->findByChallengeToken($firstChallenge->challengeToken())?->isVerified());
      $remaining = $secondChallenges->findByChallengeToken($secondChallenge->challengeToken());
      self::assertNotNull($remaining);
      self::assertFalse($remaining->isVerified());
      self::assertSame(1, $remaining->attempts());
      $envelopes = iterator_to_array($transport->get());
      self::assertCount(1, $envelopes);
      $fact = $envelopes[0]->getMessage();
      self::assertInstanceOf(OutboxEvent::class, $fact);
      self::assertInstanceOf(RecoveryCodeConsumedEvent::class, $fact->event);
      self::assertSame($userId, $fact->event->userId);
      self::assertSame('login', $fact->event->purpose);
      self::assertStringNotContainsString($code, serialize($fact));
      self::assertStringNotContainsString($hash, serialize($fact));
      $transport->ack($envelopes[0]);
    } finally {
      $firstConnection->executeStatement('DELETE FROM messenger_messages WHERE queue_name = ?', ['mfa_recovery_regression']);
      $firstConnection->executeStatement('DELETE FROM otps WHERE user_id = ?', [$userId]);
      $firstConnection->executeStatement('DELETE FROM otps WHERE user_id = ?', [$foreignOwner]);
      $firstConnection->executeStatement('DELETE FROM totp_enrollments WHERE user_id = ?', [$userId]);
      $firstManager->close();
      $secondManager->close();
      $firstConnection->close();
      $secondConnection->close();
    }
  }
}
