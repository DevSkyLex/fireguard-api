<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Infrastructure\Adapter\Mfa;

use Auth\Application\UseCase\Command\Mfa\MfaChallenge\{MfaChallengeCommand, MfaChallengeResult};
use Auth\Infrastructure\Adapter\Mfa\OtpModuleChallengeGeneratorAdapter;
use DateTimeImmutable;
use Otp\Application\Contract\Challenge\{ChallengeInfo, OtpChannel, OtpPurpose};
use Otp\Application\Port\Inbound\Challenge\OtpChallengePort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test OtpModuleChallengeGeneratorAdapterTest.
 *
 * @category Adapter Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(OtpModuleChallengeGeneratorAdapter::class)]
final class OtpModuleChallengeGeneratorAdapterTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testGenerateMapsChallengeInfo(): void
  {
    $challengeInfo = new ChallengeInfo(
      challengeToken: 'challenge-1',
      maskedRecipient: 'u***@example.com',
      expiresAt: new DateTimeImmutable('2024-01-01T00:00:00+00:00'),
      maxAttempts: 3,
    );

    $challengePort = $this->createMock(OtpChallengePort::class);
    $challengePort->expects(self::once())
      ->method('generate')
      ->with(
        'user-1',
        OtpPurpose::LOGIN,
        OtpChannel::EMAIL,
        'user@example.com',
        300,
        null,
      )
      ->willReturn($challengeInfo);

    $adapter = new OtpModuleChallengeGeneratorAdapter($challengePort);

    $result = $adapter->generate(new MfaChallengeCommand(
      userId: 'user-1',
      purpose: 'login',
      channel: 'email',
      recipient: 'user@example.com',
      ttlSeconds: 300,
    ));

    self::assertInstanceOf(MfaChallengeResult::class, $result);
    self::assertSame('challenge-1', $result->challengeToken);
    self::assertSame('u***@example.com', $result->maskedRecipient);
    self::assertSame($challengeInfo->expiresAt, $result->expiresAt);
    self::assertSame(3, $result->maxAttempts);
  }
  // #endregion
}
