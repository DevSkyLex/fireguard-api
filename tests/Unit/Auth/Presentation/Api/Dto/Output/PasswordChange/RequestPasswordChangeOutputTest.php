<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Presentation\Api\Dto\Output\PasswordChange;

use Auth\Presentation\Api\Dto\Output\PasswordChange\RequestPasswordChangeOutput;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test RequestPasswordChangeOutputTest.
 *
 * @category Output DTO Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(RequestPasswordChangeOutput::class)]
final class RequestPasswordChangeOutputTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testItExposesTheFullChallengePayload(): void
  {
    $expiresAt = new DateTimeImmutable('2026-07-07T09:15:00+00:00');

    $output = new RequestPasswordChangeOutput(
      success: true,
      message: 'A verification code has been sent to your email address.',
      challengeToken: 'abc123def456',
      maskedRecipient: 'j***e@example.com',
      expiresAt: $expiresAt,
      maxAttempts: 5,
    );

    self::assertTrue($output->success);
    self::assertSame('A verification code has been sent to your email address.', $output->message);
    self::assertSame('abc123def456', $output->challengeToken);
    self::assertSame('j***e@example.com', $output->maskedRecipient);
    self::assertSame($expiresAt, $output->expiresAt);
    self::assertSame(5, $output->maxAttempts);
  }

  #[Test]
  public function testOptionalFieldsDefaultToNull(): void
  {
    $output = new RequestPasswordChangeOutput(
      success: false,
      message: 'Unable to process the request.',
    );

    self::assertFalse($output->success);
    self::assertNull($output->challengeToken);
    self::assertNull($output->maskedRecipient);
    self::assertNull($output->expiresAt);
    self::assertNull($output->maxAttempts);
  }
  // #endregion
}
