<?php

declare(strict_types=1);

namespace Tests\Unit\Otp\Infrastructure\Persistence\Doctrine\Mapper;

use DateTimeImmutable;
use Otp\Domain\Model\Otp;
use Otp\Domain\ValueObject\{OtpChannel, OtpId, OtpPurpose};
use Otp\Infrastructure\Persistence\Doctrine\Mapper\OtpMapper;
use Otp\Infrastructure\Persistence\Doctrine\Record\OtpRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test OtpMapperTest.
 *
 * @category Mapper Tests
 */
#[CoversClass(className: OtpMapper::class)]
final class OtpMapperTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testToRecordMapsOtp(): void
  {
    $otp = Otp::generate(
      id: new OtpId('123e4567-e89b-12d3-a456-426614174000'),
      userId: 'user-1',
      purpose: OtpPurpose::LOGIN,
      channel: OtpChannel::EMAIL,
      recipient: 'user@example.com',
    );

    $mapper = new OtpMapper();
    $record = $mapper->toRecord($otp);

    self::assertSame($otp->id()->value, $record->getId());
    self::assertSame($otp->userId(), $record->getUserId());
    self::assertSame($otp->purpose()->value, $record->getPurpose());
    self::assertSame($otp->channel()->value, $record->getChannel());
  }

  #[Test]
  public function testToDomainMapsRecord(): void
  {
    $record = new OtpRecord();
    $record
      ->setId('123e4567-e89b-12d3-a456-426614174000')
      ->setUserId('user-1')
      ->setChallengeToken('challenge')
      ->setPurpose(OtpPurpose::LOGIN->value)
      ->setChannel(OtpChannel::EMAIL->value)
      ->setCodeHash('$2y$10$hashed')
      ->setRecipient('user@example.com')
      ->setExpiresAt(new DateTimeImmutable('+5 minutes'))
      ->setAttempts(1)
      ->setMaxAttempts(5)
      ->setVerifiedAt(null)
      ->setCreatedAt(new DateTimeImmutable('2024-01-01 00:00:00'));

    $mapper = new OtpMapper();
    $otp = $mapper->toDomain($record);

    self::assertInstanceOf(Otp::class, $otp);
    self::assertSame('user-1', $otp->userId());
    self::assertSame(OtpPurpose::LOGIN, $otp->purpose());
  }
  // #endregion
}
