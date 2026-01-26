<?php

declare(strict_types=1);

namespace Tests\Unit\Otp\Domain\Model;

use DateTimeImmutable;
use Otp\Domain\Exception\{OtpExpiredException, OtpMaxAttemptsException};
use Otp\Domain\Model\Otp;
use Otp\Domain\ValueObject\{ChallengeToken, OtpChannel, OtpCode, OtpId, OtpPurpose};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test OtpTest.
 *
 * @category Unit Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(Otp::class)]
final class OtpTest extends TestCase
{
  #[Test]
  public function testGenerateCreatesOtp(): void
  {
    $otp = Otp::generate(
      id: new OtpId('123e4567-e89b-12d3-a456-426614174000'),
      userId: 'user-123',
      purpose: OtpPurpose::LOGIN,
      channel: OtpChannel::EMAIL,
      recipient: 'test@example.com',
    );

    self::assertEquals('user-123', $otp->userId());
    self::assertEquals(OtpPurpose::LOGIN, $otp->purpose());
    self::assertEquals(OtpChannel::EMAIL, $otp->channel());
    self::assertEquals('test@example.com', $otp->recipient());
    self::assertEquals('pending', $otp->status());
    self::assertFalse($otp->isExpired());
    self::assertFalse($otp->isVerified());
    self::assertTrue($otp->hasAttemptsRemaining());
  }

  #[Test]
  public function testVerifyWithCorrectCode(): void
  {
    $otp = Otp::generate(
      id: new OtpId('123e4567-e89b-12d3-a456-426614174000'),
      userId: 'user-123',
      purpose: OtpPurpose::LOGIN,
      channel: OtpChannel::EMAIL,
      recipient: 'test@example.com',
    );

    // Get the plain code before it's lost
    $code = $otp->code()->plain();

    $result = $otp->verify($code);

    self::assertTrue($result);
    self::assertTrue($otp->isVerified());
    self::assertEquals('verified', $otp->status());
  }

  #[Test]
  public function testVerifyWithIncorrectCode(): void
  {
    $otp = Otp::generate(
      id: new OtpId('123e4567-e89b-12d3-a456-426614174000'),
      userId: 'user-123',
      purpose: OtpPurpose::LOGIN,
      channel: OtpChannel::EMAIL,
      recipient: 'test@example.com',
    );

    $result = $otp->verify('000000');

    self::assertFalse($result);
    self::assertFalse($otp->isVerified());
    self::assertEquals(1, $otp->attempts());
  }

  #[Test]
  public function testVerifyExceedsMaxAttempts(): void
  {
    $otp = Otp::generate(
      id: new OtpId('123e4567-e89b-12d3-a456-426614174000'),
      userId: 'user-123',
      purpose: OtpPurpose::SENSITIVE_OPERATION,
      channel: OtpChannel::EMAIL,
      recipient: 'test@example.com',
      maxAttempts: 2,
    );

    // Use up all attempts
    $otp->verify('000000');
    $otp->verify('111111');

    self::assertFalse($otp->hasAttemptsRemaining());
    self::assertEquals('failed', $otp->status());

    $this->expectException(OtpMaxAttemptsException::class);
    $otp->verify('222222');
  }

  #[Test]
  public function testMaskedRecipientEmail(): void
  {
    $otp = Otp::generate(
      id: new OtpId('123e4567-e89b-12d3-a456-426614174000'),
      userId: 'user-123',
      purpose: OtpPurpose::LOGIN,
      channel: OtpChannel::EMAIL,
      recipient: 'john.doe@example.com',
    );

    self::assertEquals('jo******@example.com', $otp->maskedRecipient());
  }

  #[Test]
  public function testMaskedRecipientSms(): void
  {
    $otp = Otp::generate(
      id: new OtpId('123e4567-e89b-12d3-a456-426614174000'),
      userId: 'user-123',
      purpose: OtpPurpose::PHONE_VERIFICATION,
      channel: OtpChannel::SMS,
      recipient: '+33612345678',
    );

    self::assertStringEndsWith('5678', $otp->maskedRecipient());
  }

  #[Test]
  public function testMaskedRecipientTotp(): void
  {
    $otp = Otp::generate(
      id: new OtpId('123e4567-e89b-12d3-a456-426614174000'),
      userId: 'user-123',
      purpose: OtpPurpose::LOGIN,
      channel: OtpChannel::TOTP,
      recipient: 'authenticator',
    );

    self::assertEquals('Authenticator App', $otp->maskedRecipient());
  }

  #[Test]
  public function testCustomTtl(): void
  {
    $otp = Otp::generate(
      id: new OtpId('123e4567-e89b-12d3-a456-426614174000'),
      userId: 'user-123',
      purpose: OtpPurpose::LOGIN,
      channel: OtpChannel::EMAIL,
      recipient: 'test@example.com',
      ttlSeconds: 60,
    );

    $expiresAt = $otp->expiresAt();
    $now = new DateTimeImmutable();

    // Should expire within ~1 minute
    $diff = $expiresAt->getTimestamp() - $now->getTimestamp();
    self::assertLessThanOrEqual(61, $diff);
    self::assertGreaterThanOrEqual(59, $diff);
  }

  #[Test]
  public function testReleaseEvents(): void
  {
    $otp = Otp::generate(
      id: new OtpId('123e4567-e89b-12d3-a456-426614174000'),
      userId: 'user-123',
      purpose: OtpPurpose::LOGIN,
      channel: OtpChannel::EMAIL,
      recipient: 'test@example.com',
    );

    $events = $otp->releaseEvents();

    self::assertCount(1, $events);
    self::assertEquals('Otp', $events[0]->aggregateType());
  }

  #[Test]
  public function testReconstitutePreservesState(): void
  {
    $createdAt = new DateTimeImmutable('-2 minutes');
    $expiresAt = new DateTimeImmutable('+10 minutes');

    $otp = Otp::reconstitute(
      id: new OtpId('123e4567-e89b-12d3-a456-426614174111'),
      challengeToken: ChallengeToken::fromString('token-111'),
      userId: 'user-111',
      purpose: OtpPurpose::LOGIN,
      channel: OtpChannel::EMAIL,
      codeHash: OtpCode::generate()->hash(),
      recipient: 'user@example.com',
      expiresAt: $expiresAt,
      maxAttempts: 3,
      attempts: 1,
      verifiedAt: null,
      createdAt: $createdAt,
    );

    self::assertSame('token-111', $otp->challengeToken()->value);
    self::assertSame('user-111', $otp->userId());
    self::assertSame($createdAt, $otp->createdAt());
    self::assertSame('pending', $otp->status());
    self::assertSame(2, $otp->attemptsRemaining());
  }

  #[Test]
  public function testStatusExpiredAndCannotVerify(): void
  {
    $otp = Otp::reconstitute(
      id: new OtpId('123e4567-e89b-12d3-a456-426614174112'),
      challengeToken: ChallengeToken::fromString('token-112'),
      userId: 'user-112',
      purpose: OtpPurpose::LOGIN,
      channel: OtpChannel::EMAIL,
      codeHash: OtpCode::generate()->hash(),
      recipient: 'user@example.com',
      expiresAt: new DateTimeImmutable('-1 minute'),
      maxAttempts: 3,
      attempts: 0,
      verifiedAt: null,
      createdAt: new DateTimeImmutable('-2 minutes'),
    );

    self::assertTrue($otp->isExpired());
    self::assertSame('expired', $otp->status());
    self::assertFalse($otp->canVerify());
  }

  #[Test]
  public function testMaskedRecipientEmailShortLocalPart(): void
  {
    $otp = Otp::generate(
      id: new OtpId('123e4567-e89b-12d3-a456-426614174113'),
      userId: 'user-123',
      purpose: OtpPurpose::LOGIN,
      channel: OtpChannel::EMAIL,
      recipient: 'ab@example.com',
    );

    self::assertSame('**@example.com', $otp->maskedRecipient());
  }

  #[Test]
  public function testMaskedRecipientPhoneShortNumber(): void
  {
    $otp = Otp::generate(
      id: new OtpId('123e4567-e89b-12d3-a456-426614174114'),
      userId: 'user-123',
      purpose: OtpPurpose::PHONE_VERIFICATION,
      channel: OtpChannel::SMS,
      recipient: '123',
    );

    self::assertSame('****', $otp->maskedRecipient());
  }

  #[Test]
  public function testVerifyThrowsWhenExpired(): void
  {
    $otp = Otp::reconstitute(
      id: new OtpId('123e4567-e89b-12d3-a456-426614174115'),
      challengeToken: ChallengeToken::fromString('token-115'),
      userId: 'user-115',
      purpose: OtpPurpose::LOGIN,
      channel: OtpChannel::EMAIL,
      codeHash: OtpCode::generate()->hash(),
      recipient: 'user@example.com',
      expiresAt: new DateTimeImmutable('-1 minute'),
      maxAttempts: 3,
      attempts: 0,
      verifiedAt: null,
      createdAt: new DateTimeImmutable('-2 minutes'),
    );

    $this->expectException(OtpExpiredException::class);

    $otp->verify('123456');
  }
}
