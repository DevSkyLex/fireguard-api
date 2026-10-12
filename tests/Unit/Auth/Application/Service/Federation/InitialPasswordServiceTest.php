<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Application\Service\Federation;

use Auth\Application\Service\Federation\InitialPasswordService;
use Otp\Application\Port\Inbound\Challenge\OtpChallengePort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;
use User\Application\Port\Inbound\FederatedUserPort;
use User\Infrastructure\Adapter\Policy\ConfiguredPasswordPolicyAdapter;

/**
 * Class InitialPasswordServiceTest
 *
 * Guards one-time challenge consumption when an invalid password arrives through a command.
 *
 * @category Service Tests
 */
#[CoversClass(InitialPasswordService::class)]
final class InitialPasswordServiceTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testRejectedPasswordDoesNotConsumeChallengeOrSetCredential(): void
  {
    $users = $this->createMock(FederatedUserPort::class);
    $users->expects(self::never())->method('setInitialPassword');
    $challenges = $this->createMock(OtpChallengePort::class);
    $challenges->expects(self::never())->method('verifyFor');
    $service = new InitialPasswordService($users, $challenges, new ConfiguredPasswordPolicyAdapter());

    $this->expectException(InvalidValueException::class);
    $service->confirm('user', 'challenge', '123456', 'weak');
  }
  // #endregion
}
