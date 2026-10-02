<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Domain\ValueObject;

use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
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

  /**
   * Method browserAndOsSamples.
   *
   * @static
   *
   * @return array<string, array{string, ?string, ?string}> user agent, browser and operating system
   */
  public static function browserAndOsSamples(): array
  {
    return [
      'Chrome on Windows' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36', 'Chrome', 'Windows'],
      'Edge on Windows (also says Chrome and Safari)' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36 Edg/126.0.0.0', 'Edge', 'Windows'],
      'Opera on Windows (also says Chrome and Safari)' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36 OPR/112.0.0.0', 'Opera', 'Windows'],
      'Firefox on Linux' => ['Mozilla/5.0 (X11; Linux x86_64; rv:127.0) Gecko/20100101 Firefox/127.0', 'Firefox', 'Linux'],
      'Chrome on Android (also says Linux)' => ['Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Mobile Safari/537.36', 'Chrome', 'Android'],
      'Safari on macOS' => ['Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Safari/605.1.15', 'Safari', 'macOS'],
      'Chrome on iOS' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/126.0.6478.153 Mobile/15E148 Safari/604.1', 'Chrome', 'iOS'],
      'Firefox on iOS' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) FxiOS/127.0 Mobile/15E148 Safari/605.1.15', 'Firefox', 'iOS'],
      'Edge on Android' => ['Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Mobile Safari/537.36 EdgA/126.0.0.0', 'Edge', 'Android'],
      'Internet Explorer 11' => ['Mozilla/5.0 (Windows NT 10.0; WOW64; Trident/7.0; rv:11.0) like Gecko', 'IE', 'Windows'],
    ];
  }

  #[Test]
  #[DataProvider('browserAndOsSamples')]
  public function testNamesTheMostSpecificBrowserAndOs(string $userAgent, ?string $browser, ?string $operatingSystem): void
  {
    $ua = new UserAgent($userAgent);

    self::assertSame($browser, $ua->getBrowser());
    self::assertSame($operatingSystem, $ua->getOS());
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
  public function testZeroIsAValidUnknownUserAgent(): void
  {
    $agent = new UserAgent('0');
    self::assertSame('0', $agent->value);
    self::assertNull($agent->getBrowser());
    self::assertNull($agent->getOS());
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
