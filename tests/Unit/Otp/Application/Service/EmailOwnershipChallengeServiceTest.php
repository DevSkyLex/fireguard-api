<?php

declare(strict_types=1);

namespace Tests\Unit\Otp\Application\Service;

use Otp\Application\Contract\Challenge\{OtpPurpose, VerificationInfo};
use Otp\Application\Port\Inbound\Challenge\OtpChallengePort;
use Otp\Application\Service\EmailOwnershipChallengeService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\TransactionManagerPort;

/**
 * Test EmailOwnershipChallengeServiceTest.
 *
 * @category Test
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class EmailOwnershipChallengeServiceTest extends TestCase
{
  // #region Tests
  /**
   * @since 1.0.0
   */
  #[Test]
  public function transactionCommitsFailedAttemptsAndBindsRecipient(): void
  {
    $transaction = $this->createMock(TransactionManagerPort::class);
    $transaction->expects(self::once())->method('transactional')->willReturnCallback(static fn (callable $operation): mixed => $operation());
    $otp = $this->createMock(OtpChallengePort::class);
    $otp->expects(self::once())->method('verifyFor')->with('token', '123456', 'user', OtpPurpose::EMAIL_OWNERSHIP, 'member@business.example')->willReturn(new VerificationInfo(false, 4));
    $result = new EmailOwnershipChallengeService($otp, $transaction)->verify('user', 'member@business.example', 'token', '123456');
    self::assertFalse($result->success);
    self::assertSame(4, $result->attemptsRemaining);
  }
  // #endregion
}
