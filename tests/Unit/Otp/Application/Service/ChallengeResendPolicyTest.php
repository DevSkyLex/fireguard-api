<?php

declare(strict_types=1);

namespace Tests\Unit\Otp\Application\Service;

use DateTimeImmutable;
use Otp\Application\Service\ChallengeResendPolicy;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test ChallengeResendPolicyTest.
 *
 * @category Service Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ChallengeResendPolicy::class)]
final class ChallengeResendPolicyTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testCanResendInReturnsRemainingSeconds(): void
  {
    $createdAt = new DateTimeImmutable('2024-01-01T00:00:00+00:00');
    $now = $createdAt->modify('+10 seconds');

    $remaining = ChallengeResendPolicy::canResendIn($createdAt, $now);

    self::assertSame(50, $remaining);
  }

  #[Test]
  public function testCanResendInDoesNotReturnNegative(): void
  {
    $createdAt = new DateTimeImmutable('2024-01-01T00:00:00+00:00');
    $now = $createdAt->modify('+120 seconds');

    $remaining = ChallengeResendPolicy::canResendIn($createdAt, $now);

    self::assertSame(0, $remaining);
  }
  // #endregion
}
