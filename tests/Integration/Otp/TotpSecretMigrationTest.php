<?php

declare(strict_types=1);

namespace Tests\Integration\Otp;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Otp\Domain\Model\Totp\TotpEnrollment;
use Otp\Domain\ValueObject\TotpSecret;
use Otp\Infrastructure\Adapter\Crypto\OpensslTotpSecretCipherAdapter;
use Otp\Infrastructure\Console\MigrateTotpSecretsCommand;
use Otp\Infrastructure\Persistence\Doctrine\Mapper\TotpEnrollmentMapper;
use Otp\Infrastructure\Persistence\Doctrine\Record\TotpEnrollmentRecord;
use Otp\Infrastructure\Persistence\Doctrine\Repository\TotpEnrollmentRepository;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

use function base64_encode;
use function str_repeat;

/**
 * Test TotpSecretMigrationTest.
 *
 * @category Integration Tests
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class TotpSecretMigrationTest extends KernelTestCase
{
  private const string ID = 'c9000000-0000-4000-8000-000000000031';

  private const string ACTIVE = 'JBSWY3DPEHPK3PXP';

  private const string PENDING = 'KRSXG5CTMVRXEZLU';

  #[Test]
  public function migratesAndRotatesBothSecretsWithoutChangingEnrollmentOrLockoutState(): void
  {
    self::bootKernel();
    /** @var EntityManagerInterface $em */
    $em = static::getContainer()->get('doctrine.orm.auth_entity_manager');
    $now = new DateTimeImmutable('2026-09-20T12:00:00+00:00');
    $enrollment = TotpEnrollment::reconstitute(
      self::ID,
      new TotpSecret(self::ACTIVE),
      $now,
      new TotpSecret(self::PENDING),
      $now,
      2,
      5,
      $now,
      $now,
      4,
      $now->modify('+15 minutes'),
    );
    $legacyMapper = new TotpEnrollmentMapper(new OpensslTotpSecretCipherAdapter([], ''));
    new TotpEnrollmentRepository($em, $legacyMapper)->save($enrollment);
    $oldKey = base64_encode(str_repeat('a', 32));
    $oldCipher = new OpensslTotpSecretCipherAdapter(['old' => $oldKey], 'old');
    $oldMapper = new TotpEnrollmentMapper($oldCipher);
    $command = new CommandTester(new MigrateTotpSecretsCommand($em, $oldMapper, $oldCipher));

    self::assertSame(1, $command->execute(['--verify-only' => true]));
    self::assertSame(0, $command->execute(['--batch-size' => '1']));
    self::assertStringNotContainsString(self::ACTIVE, $command->getDisplay());
    self::assertStringNotContainsString(self::PENDING, $command->getDisplay());
    self::assertStringNotContainsString(self::ID, $command->getDisplay());
    self::assertSame(0, $command->execute(['--verify-only' => true]));

    $record = $em->find(TotpEnrollmentRecord::class, self::ID);
    self::assertNotNull($record);
    self::assertTrue($record->secretsEncrypted);
    self::assertNull($record->getActiveSecret());
    self::assertNull($record->getPendingSecret());
    self::assertNotNull($record->activeSecretCiphertext);
    $firstEnvelope = $record->activeSecretCiphertext;
    self::assertSame(0, $command->execute([]));
    self::assertSame($firstEnvelope, $em->find(TotpEnrollmentRecord::class, self::ID)?->activeSecretCiphertext);

    $newKey = base64_encode(str_repeat('b', 32));
    $newCipher = new OpensslTotpSecretCipherAdapter(['old' => $oldKey, 'new' => $newKey], 'new');
    $newMapper = new TotpEnrollmentMapper($newCipher);
    $rotate = new CommandTester(new MigrateTotpSecretsCommand($em, $newMapper, $newCipher));
    self::assertSame(0, $rotate->execute(['--batch-size' => '1']));
    self::assertSame(0, $rotate->execute(['--verify-only' => true]));
    $onlyNewMapper = new TotpEnrollmentMapper(new OpensslTotpSecretCipherAdapter(['new' => $newKey], 'new'));
    $restored = new TotpEnrollmentRepository($em, $onlyNewMapper)->findByUserId(self::ID);

    self::assertNotNull($restored);
    self::assertSame(self::ACTIVE, $restored->activeSecret()?->secret);
    self::assertSame(self::PENDING, $restored->pendingSecret()?->secret);
    self::assertSame(2, $restored->attempts());
    self::assertSame(4, $restored->disableAttempts());
    self::assertEquals($now->modify('+15 minutes'), $restored->disableLockedUntil());
    self::assertEquals($now, $restored->activeConfirmedAt());
  }
}
