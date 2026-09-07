<?php

declare(strict_types=1);

namespace Tests\Unit\Otp\Application\UseCase\Command\Challenge\VerifyOtp;

use Otp\Application\Exception\OtpNotFoundException;
use Otp\Application\Port\Outbound\Challenge\OtpRepositoryPort;
use Otp\Application\Port\Outbound\Totp\{TotpEnrollmentRepositoryPort, TotpServicePort};
use Otp\Application\UseCase\Command\Challenge\VerifyOtp\{VerifyOtpCommand, VerifyOtpHandler};
use Otp\Domain\Model\Otp;
use Otp\Domain\ValueObject\{OtpChannel, OtpId, OtpPurpose};
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test EmailOwnershipBindingTest.
 *
 * @category Test
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class EmailOwnershipBindingTest extends TestCase
{
  // #region Tests
  /**
   * @since 1.0.0
   *
   * @param string $userId expected challenge owner
   * @param string $recipient expected current mailbox
   * @param OtpPurpose $purpose expected purpose
   */
  #[Test]
  #[DataProvider('mismatches')]
  public function mismatchedBindingIsDeniedWithoutConsumingCode(string $userId, string $recipient, OtpPurpose $purpose): void
  {
    $otp = $this->challenge();
    $repository = $this->createMock(OtpRepositoryPort::class);
    $repository->method('findByChallengeToken')->willReturn($otp);
    $repository->expects(self::never())->method('save');
    $handler = new VerifyOtpHandler($repository, $this->createStub(TotpEnrollmentRepositoryPort::class), $this->createStub(TotpServicePort::class));
    $this->expectException(OtpNotFoundException::class);
    $handler(new VerifyOtpCommand(code: $otp->code()->plain(), challengeToken: $otp->challengeToken()->value, expectedUserId: $userId, expectedPurpose: $purpose, expectedRecipient: $recipient));
  }

  /**
   * @since 1.0.0
   *
   * @return array<string, array{string, string, OtpPurpose}>
   */
  public static function mismatches(): array
  {
    return [
      'other account' => ['other', 'member@business.example', OtpPurpose::EMAIL_OWNERSHIP],
      'old mailbox' => ['user', 'new@business.example', OtpPurpose::EMAIL_OWNERSHIP],
      'wrong purpose' => ['user', 'member@business.example', OtpPurpose::SENSITIVE_OPERATION],
    ];
  }

  /**
   * @since 1.0.0
   */
  #[Test]
  public function verifiedEmailCodeCannotBeReplayed(): void
  {
    $otp = $this->challenge();
    self::assertTrue($otp->verify($otp->code()->plain()));
    self::assertFalse($otp->verify($otp->code()->plain()));
  }

  /**
   * @since 1.0.0
   */
  #[Test]
  public function consumedExternalCodeCannotBeReplayed(): void
  {
    $otp = $this->challenge();
    self::assertTrue($otp->verifyExternal(true));
    self::assertFalse($otp->verifyExternal(true));
  }

  /**
   * @since 1.0.0
   *
   * @return Otp the test email challenge
   */
  private function challenge(): Otp
  {
    return Otp::generate(new OtpId('11111111-1111-4111-8111-111111111111'), 'user', OtpPurpose::EMAIL_OWNERSHIP, OtpChannel::EMAIL, 'member@business.example');
  }
  // #endregion
}
