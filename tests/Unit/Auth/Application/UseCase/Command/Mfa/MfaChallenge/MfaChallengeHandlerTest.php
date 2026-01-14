<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Application\UseCase\Command\Mfa\MfaChallenge;

use Auth\Application\Port\Outbound\Mfa\ChallengeGeneratorPort;
use Auth\Application\UseCase\Command\Mfa\MfaChallenge\{MfaChallengeCommand, MfaChallengeHandler, MfaChallengeResult};
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test MfaChallengeHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: MfaChallengeHandler::class)]
final class MfaChallengeHandlerTest extends TestCase
{
  // #region Methods
  /**
   * Method testInvokeReturnsGeneratedChallenge.
   */
  #[Test]
  public function testInvokeReturnsGeneratedChallenge(): void
  {
    $command = new MfaChallengeCommand(
      userId: 'user-123',
      purpose: 'login',
      channel: 'email',
      recipient: 'user@example.com',
    );

    $result = new MfaChallengeResult(
      challengeToken: 'challenge-123',
      maskedRecipient: 'u***@example.com',
      expiresAt: new DateTimeImmutable('+5 minutes'),
      maxAttempts: 3,
    );

    /** @var ChallengeGeneratorPort&MockObject $generator */
    $generator = $this->createMock(ChallengeGeneratorPort::class);
    $generator->expects(self::once())
      ->method('generate')
      ->with($command)
      ->willReturn($result);

    $handler = new MfaChallengeHandler(challengeGenerator: $generator);

    $this->assertSame($result, $handler->__invoke($command));
  }
  // #endregion
}
