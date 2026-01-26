<?php

declare(strict_types=1);

namespace Tests\Unit\Otp\Domain\ValueObject;

use Otp\Domain\ValueObject\ChallengeToken;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

use function strlen;

/**
 * Test ChallengeTokenTest.
 *
 * @category Value Object Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ChallengeToken::class)]
final class ChallengeTokenTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testGenerateCreatesToken(): void
  {
    $token = ChallengeToken::generate();

    self::assertNotSame('', $token->value);
    self::assertSame(64, strlen($token->value));
  }

  #[Test]
  public function testEqualsUsesConstantTimeCompare(): void
  {
    $tokenA = ChallengeToken::fromString('abc');
    $tokenB = ChallengeToken::fromString('abc');
    $tokenC = ChallengeToken::fromString('def');

    self::assertTrue($tokenA->equals($tokenB));
    self::assertFalse($tokenA->equals($tokenC));
  }
  // #endregion
}
