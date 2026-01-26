<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Infrastructure\Adapter\Mfa;

use Auth\Application\UseCase\Command\Mfa\MfaVerify\MfaVerifyResult;
use Auth\Infrastructure\Adapter\Mfa\OtpModuleChallengeVerifierAdapter;
use Otp\Application\Contract\Challenge\VerificationInfo;
use Otp\Application\Port\Inbound\Challenge\OtpChallengePort;
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

    $challengePort = $this->createMock(OtpChallengePort::class);
    $challengePort->expects(self::once())
      ->method('verify')
      ->with('challenge-1', '123456')
      ->willReturn($verificationInfo);

    $adapter = new OtpModuleChallengeVerifierAdapter($challengePort);

    $result = $adapter->verify('challenge-1', '123456');

    self::assertInstanceOf(MfaVerifyResult::class, $result);
    self::assertTrue($result->success);
    self::assertSame(1, $result->attemptsRemaining);
    self::assertNull($result->error);
  }
  // #endregion
}
