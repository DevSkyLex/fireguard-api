<?php

declare(strict_types=1);

namespace Tests\Unit\Otp\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Otp\Infrastructure\Persistence\Doctrine\Record\OtpRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test OtpRecordTest.
 *
 * @category Record Tests
 */
#[CoversClass(className: OtpRecord::class)]
final class OtpRecordTest extends TestCase
{
  // #region Tests
  #[Test]
  public function testSettersAndGetters(): void
  {
    $record = new OtpRecord();
    $expiresAt = new DateTimeImmutable('+5 minutes');
    $createdAt = new DateTimeImmutable('2024-01-01 00:00:00');

    $record
      ->setId('123e4567-e89b-12d3-a456-426614174000')
      ->setUserId('user-1')
      ->setChallengeToken('challenge-token')
      ->setPurpose('login')
      ->setChannel('email')
      ->setCodeHash('hash')
      ->setRecipient('user@example.com')
      ->setExpiresAt($expiresAt)
      ->setAttempts(2)
      ->setMaxAttempts(5)
      ->setVerifiedAt(null)
      ->setCreatedAt($createdAt);

    self::assertSame('123e4567-e89b-12d3-a456-426614174000', $record->getId());
    self::assertSame('user-1', $record->getUserId());
    self::assertSame('challenge-token', $record->getChallengeToken());
    self::assertSame('login', $record->getPurpose());
    self::assertSame('email', $record->getChannel());
    self::assertSame('hash', $record->getCodeHash());
    self::assertSame('user@example.com', $record->getRecipient());
    self::assertSame($expiresAt, $record->getExpiresAt());
    self::assertSame(2, $record->getAttempts());
    self::assertSame(5, $record->getMaxAttempts());
    self::assertNull($record->getVerifiedAt());
    self::assertSame($createdAt, $record->getCreatedAt());
  }
  // #endregion
}
