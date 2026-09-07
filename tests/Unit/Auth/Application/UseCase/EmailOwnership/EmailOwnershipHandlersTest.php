<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Application\UseCase\EmailOwnership;

use Auth\Application\UseCase\Command\EmailOwnership\ConfirmEmailOwnership\{ConfirmEmailOwnershipCommand, ConfirmEmailOwnershipHandler};
use Auth\Application\UseCase\Command\EmailOwnership\StartEmailOwnership\{StartEmailOwnershipCommand, StartEmailOwnershipHandler};
use Auth\Application\UseCase\Query\EmailOwnership\GetEmailOwnership\{GetEmailOwnershipHandler, GetEmailOwnershipQuery};
use Auth\Domain\Exception\EmailOwnershipException;
use DateTimeImmutable;
use Otp\Application\Contract\Challenge\{ChallengeInfo, OtpChannel, OtpPurpose, VerificationInfo};
use Otp\Application\Port\Inbound\Challenge\{EmailOwnershipChallengePort, OtpChallengePort};
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use User\Application\Contract\EmailOwnershipResult;
use User\Application\Port\Inbound\EmailOwnershipPort;
use User\Domain\Exception\EmailOwnershipUnavailableException;

/**
 * Test EmailOwnershipHandlersTest.
 *
 * @category Test
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class EmailOwnershipHandlersTest extends TestCase
{
  // #region Tests
  /**
   * @since 1.0.0
   */
  #[Test]
  public function statusUsesProofRatherThanProviderVerification(): void
  {
    $ownership = $this->createMock(EmailOwnershipPort::class);
    $ownership->expects(self::once())->method('get')->with('user')->willReturn(new EmailOwnershipResult('user', 'member@business.example', false));
    self::assertFalse((new GetEmailOwnershipHandler($ownership))(new GetEmailOwnershipQuery('user'))->verified);
  }

  /**
   * @since 1.0.0
   */
  #[Test]
  public function startUsesCurrentAddressAndDedicatedEmailPurpose(): void
  {
    $ownership = $this->createStub(EmailOwnershipPort::class);
    $ownership->method('get')->willReturn(new EmailOwnershipResult('user', 'member@business.example', false));
    $challenges = $this->createMock(OtpChallengePort::class);
    $challenges->expects(self::once())->method('generate')->with('user', OtpPurpose::EMAIL_OWNERSHIP, OtpChannel::EMAIL, 'member@business.example')
      ->willReturn(new ChallengeInfo('token', 'm***@business.example', new DateTimeImmutable('+10 minutes'), 5));
    $result = (new StartEmailOwnershipHandler($ownership, $challenges))(new StartEmailOwnershipCommand('user'));
    self::assertSame('token', $result->challengeToken);
    self::assertSame(60, $result->canResendIn);
  }

  /**
   * @since 1.0.0
   */
  #[Test]
  public function confirmationBindsAccountAndCurrentAddress(): void
  {
    $ownership = $this->createMock(EmailOwnershipPort::class);
    $ownership->method('get')->willReturn(new EmailOwnershipResult('user', 'member@business.example', false));
    $ownership->expects(self::once())->method('confirm')->with('user', 'member@business.example');
    $challenges = $this->createMock(EmailOwnershipChallengePort::class);
    $challenges->expects(self::once())->method('verify')->with('user', 'member@business.example', 'token', '123456')->willReturn(new VerificationInfo(true));
    self::assertTrue((new ConfirmEmailOwnershipHandler($ownership, $challenges))(new ConfirmEmailOwnershipCommand('user', 'token', '123456'))->verified);
  }

  /**
   * @since 1.0.0
   */
  #[Test]
  public function failedChallengeCannotGrantProof(): void
  {
    $ownership = $this->createMock(EmailOwnershipPort::class);
    $ownership->method('get')->willReturn(new EmailOwnershipResult('user', 'member@business.example', false));
    $ownership->expects(self::never())->method('confirm');
    $challenges = $this->createStub(EmailOwnershipChallengePort::class);
    $challenges->method('verify')->willReturn(new VerificationInfo(false));
    $this->expectException(EmailOwnershipException::class);
    (new ConfirmEmailOwnershipHandler($ownership, $challenges))(new ConfirmEmailOwnershipCommand('user', 'token', '123456'));
  }

  /**
   * @since 1.0.0
   */
  #[Test]
  public function concurrentEmailChangeFailsClosedAfterOtpConsumption(): void
  {
    $ownership = $this->createMock(EmailOwnershipPort::class);
    $ownership->method('get')->willReturn(new EmailOwnershipResult('user', 'old@business.example', false));
    $ownership->expects(self::once())->method('confirm')->with('user', 'old@business.example')->willThrowException(EmailOwnershipUnavailableException::unavailable());
    $challenges = $this->createStub(EmailOwnershipChallengePort::class);
    $challenges->method('verify')->willReturn(new VerificationInfo(true));
    $this->expectException(EmailOwnershipUnavailableException::class);
    (new ConfirmEmailOwnershipHandler($ownership, $challenges))(new ConfirmEmailOwnershipCommand('user', 'token', '123456'));
  }
  // #endregion
}
