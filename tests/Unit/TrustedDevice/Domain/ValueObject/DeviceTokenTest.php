<?php

declare(strict_types=1);

namespace Tests\Unit\TrustedDevice\Domain\ValueObject;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;
use TrustedDevice\Domain\ValueObject\DeviceToken;

use function hash;

/**
 * Test DeviceTokenTest.
 *
 * @category Unit Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: DeviceToken::class)]
final class DeviceTokenTest extends TestCase
{
  // #region Methods
  /**
   * Method testGenerateCreatesTokenWithPlainAndHash.
   *
   * Tests that generate returns a token with
   * a valid plain value and hash.
   */
  #[Test]
  public function testGenerateCreatesTokenWithPlainAndHash(): void
  {
    $token = DeviceToken::generate();

    $plain = $token->plain();

    self::assertNotSame('', $plain);
    self::assertSame(hash('sha256', $plain), $token->hash);
    self::assertTrue($token->verify($plain));
    self::assertFalse($token->verify('invalid-token'));
  }

  /**
   * Method testPlainThrowsWhenNotAvailable.
   *
   * Tests that plain token is unavailable
   * when built from a hash only.
   */
  #[Test]
  public function testPlainThrowsWhenNotAvailable(): void
  {
    $token = DeviceToken::fromHash(hash('sha256', 'plain-token'));

    $this->expectException(InvalidValueException::class);
    $token->plain();
  }
  // #endregion
}
