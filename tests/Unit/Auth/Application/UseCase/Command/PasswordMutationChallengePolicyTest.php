<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Application\UseCase\Command;

use Auth\Application\Port\Outbound\TokenRevocationPort;
use Auth\Application\UseCase\Command\PasswordChange\ConfirmPasswordChange\{ConfirmPasswordChangeCommand, ConfirmPasswordChangeHandler};
use Auth\Application\UseCase\Command\PasswordReset\ConfirmPasswordReset\{ConfirmPasswordResetCommand, ConfirmPasswordResetHandler};
use Otp\Application\Port\Inbound\Challenge\{ChallengeAccessPort, OtpChallengePort};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Session\Application\Port\Inbound\SessionRevocationPort;
use Shared\Domain\Exception\InvalidValueException;
use User\Application\Port\Inbound\UserCredentialsPort;
use User\Infrastructure\Adapter\Policy\ConfiguredPasswordPolicyAdapter;

/**
 * Class PasswordMutationChallengePolicyTest
 *
 * Ensures non-HTTP invalid passwords cannot spend OTPs or revoke existing credentials.
 *
 * @category Handler Tests
 */
#[CoversClass(ConfirmPasswordResetHandler::class)]
#[CoversClass(ConfirmPasswordChangeHandler::class)]
final class PasswordMutationChallengePolicyTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testInvalidResetPasswordDoesNotConsumeChallenge(): void
  {
    $access = $this->createMock(ChallengeAccessPort::class);
    $access->expects(self::never())->method('find');
    $otp = $this->createMock(OtpChallengePort::class);
    $otp->expects(self::never())->method('verifyFor');
    $users = $this->createMock(UserCredentialsPort::class);
    $users->expects(self::never())->method('changePassword');
    $handler = new ConfirmPasswordResetHandler($access, $users, $this->createStub(SessionRevocationPort::class), $this->createStub(TokenRevocationPort::class), $otp, new ConfiguredPasswordPolicyAdapter());

    $this->expectException(InvalidValueException::class);
    $handler(new ConfirmPasswordResetCommand('challenge', '123456', 'weak'));
  }

  #[Test]
  public function testInvalidChangePasswordDoesNotConsumeChallenge(): void
  {
    $access = $this->createMock(ChallengeAccessPort::class);
    $access->expects(self::never())->method('find');
    $otp = $this->createMock(OtpChallengePort::class);
    $otp->expects(self::never())->method('verifyFor');
    $users = $this->createMock(UserCredentialsPort::class);
    $users->expects(self::never())->method('changePassword');
    $handler = new ConfirmPasswordChangeHandler($access, $users, $this->createStub(SessionRevocationPort::class), $this->createStub(TokenRevocationPort::class), $otp, new ConfiguredPasswordPolicyAdapter());

    $this->expectException(InvalidValueException::class);
    $handler(new ConfirmPasswordChangeCommand('user', 'challenge', '123456', 'weak'));
  }
  // #endregion
}
