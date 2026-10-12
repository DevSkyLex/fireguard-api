<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Infrastructure\Adapter\Mfa;

use Auth\Application\UseCase\Command\Mfa\MfaVerify\MfaVerifyResult;
use Auth\Infrastructure\Adapter\Mfa\OtpModuleChallengeVerifierAdapter;
use Otp\Application\Contract\Challenge\{OtpPurpose, VerificationInfo};
use Otp\Application\Port\Inbound\Challenge\MfaChallengeVerificationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test OtpModuleChallengeVerifierAdapterTest.
 *
 * @category Adapter Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(OtpModuleChallengeVerifierAdapter::class)]
final class OtpModuleChallengeVerifierAdapterTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testVerifyMapsChallengeInfo(): void
  {
    $verificationInfo = new VerificationInfo(
      success: true,
      attemptsRemaining: 1,
      error: null,
    );

    $challengePort = $this->createMock(MfaChallengeVerificationPort::class);
    $challengePort->expects(self::once())
      ->method('verifyFor')
      ->with('challenge-1', 'user-1', OtpPurpose::LOGIN, 'otp', '123456')
      ->willReturn($verificationInfo);

    $adapter = new OtpModuleChallengeVerifierAdapter($challengePort);

    $result = $adapter->verify('challenge-1', '123456', 'user-1');

    self::assertInstanceOf(MfaVerifyResult::class, $result);
    self::assertTrue($result->success);
    self::assertSame(1, $result->attemptsRemaining);
    self::assertNull($result->error);
  }
  // #endregion

  public function testRecoveryDenialPreservesBoundOwnerPurposeAndMethod(): void
  {
    $port = $this->createMock(MfaChallengeVerificationPort::class);
    $port->expects(self::once())->method('verifyFor')->with('token', 'user', OtpPurpose::LOGIN, 'recovery_code', 'proof')->willReturn(new VerificationInfo(false, 3, 'Invalid verification code.', 'invalid_code'));
    $result = new OtpModuleChallengeVerifierAdapter($port)->verify('token', 'proof', 'user', 'recovery_code');
    self::assertFalse($result->success);
    self::assertSame(3, $result->attemptsRemaining);
    self::assertSame('Invalid verification code.', $result->error);
  }
}
