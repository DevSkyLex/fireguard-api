<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Domain\ValueObject;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;
use Shared\Domain\ValueObject\UserAgent;

use function str_repeat;

/**
 * Test UserAgentTest.
 *
 * @category ValueObject Tests
 */
#[CoversClass(className: UserAgent::class)]
final class UserAgentTest extends TestCase
{
  // #region Tests
  #[Test]
  public function testDetectsMobileAndBrowserAndOs(): void
  {
    $uaString = 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.0 Mobile/15E148 Safari/604.1';
    $ua = new UserAgent($uaString);

    self::assertTrue($ua->isMobile());
    self::assertSame('Safari', $ua->getBrowser());
    self::assertSame('iOS', $ua->getOS());
  }

  #[Test]
  public function testDetectsBot(): void
  {
    $ua = new UserAgent('curl/7.64.1');

    self::assertTrue($ua->isBot());
    self::assertNull($ua->getBrowser());
  }

  #[Test]
  public function testEquals(): void
  {
    $uaOne = new UserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0');
    $uaTwo = new UserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0');
    $uaThree = new UserAgent('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) Firefox/120.0');

    self::assertTrue($uaOne->equals($uaTwo));
    self::assertFalse($uaOne->equals($uaThree));
  }

  #[Test]
  public function testEmptyUserAgentThrows(): void
  {
    $this->expectException(InvalidValueException::class);

    new UserAgent('');
  }

  #[Test]
  public function testTooLongUserAgentThrows(): void
  {
    $this->expectException(InvalidValueException::class);

    new UserAgent(str_repeat('a', 513));
  }
  // #endregion
}
