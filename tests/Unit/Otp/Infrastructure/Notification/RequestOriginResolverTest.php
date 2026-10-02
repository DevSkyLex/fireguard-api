<?php

declare(strict_types=1);

namespace Tests\Unit\Otp\Infrastructure\Notification;

use Otp\Infrastructure\Notification\RequestOriginResolver;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\{Request, RequestStack};

use function array_filter;
use function implode;
use function str_repeat;

/**
 * Test RequestOriginResolverTest.
 *
 * @category Notification Tests
 */
#[CoversClass(className: RequestOriginResolver::class)]
final class RequestOriginResolverTest extends TestCase
{
  private const string CHROME_ON_WINDOWS = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36';

  // #region Tests
  #[Test]
  public function testItDescribesTheRequestThatAskedForTheCode(): void
  {
    $origin = new RequestOriginResolver($this->stackWith(self::CHROME_ON_WINDOWS))->resolve();

    self::assertSame(['browser' => 'Chrome', 'operatingSystem' => 'Windows'], $origin);
  }

  #[Test]
  public function testItHasNothingToDescribeOutsideAnHttpRequest(): void
  {
    self::assertNull(new RequestOriginResolver(new RequestStack())->resolve());
    self::assertNull(new RequestOriginResolver()->resolve());
  }

  #[Test]
  public function testItKeepsWhatItRecognisesWhenTheOtherHalfIsUnknown(): void
  {
    $origin = new RequestOriginResolver($this->stackWith('Mozilla/5.0 (Unknown) Firefox/127.0'))->resolve();

    self::assertSame(['browser' => 'Firefox', 'operatingSystem' => null], $origin);
  }

  #[Test]
  public function testItKeepsTheMainRequestOriginWhenASubrequestIsActive(): void
  {
    $stack = $this->stackWith(self::CHROME_ON_WINDOWS);
    $mainRequest = $stack->getMainRequest();
    self::assertNotNull($mainRequest);
    $mainRequest->server->set('REMOTE_ADDR', '8.8.8.8');
    $mainRequest->setLocale('es');

    $subrequest = Request::create('/internal', 'GET', server: ['REMOTE_ADDR' => '1.1.1.1']);
    $subrequest->headers->set('User-Agent', 'Mozilla/5.0 (Linux) Firefox/127.0');
    $subrequest->setLocale('fr');
    $stack->push($subrequest);
    $resolver = new RequestOriginResolver($stack);

    self::assertSame(['browser' => 'Chrome', 'operatingSystem' => 'Windows'], $resolver->resolve());
    $origin = $resolver->current();
    self::assertNotNull($origin);
    self::assertSame('8.8.8.8', $origin->ipAddress);
    self::assertSame('es', $origin->locale);
    self::assertSame('Chrome', $origin->browser);
    self::assertSame('Windows', $origin->operatingSystem);
  }

  #[Test]
  public function testItReturnsNullWhenNothingIsRecognised(): void
  {
    self::assertNull(new RequestOriginResolver($this->stackWith('curl/8.4.0'))->resolve());
    self::assertNull(new RequestOriginResolver($this->stackWith(null))->resolve());
  }

  #[Test]
  public function testUnknownAndEmptyHeadersPreserveTheCurrentRequest(): void
  {
    foreach (['0', ' 0 ', '', '   ', null] as $header) {
      $stack = $this->stackWith($header);
      $request = $stack->getMainRequest();
      self::assertNotNull($request);
      $request->server->set('REMOTE_ADDR', '8.8.8.8');
      $request->setLocale('fr');
      $resolver = new RequestOriginResolver($stack);
      self::assertNull($resolver->resolve());
      $current = $resolver->current();
      self::assertNotNull($current);
      self::assertSame('8.8.8.8', $current->ipAddress);
      self::assertSame('fr', $current->locale);
      self::assertNull($current->browser);
      self::assertNull($current->operatingSystem);
    }
  }

  #[Test]
  public function testAnOversizedUserAgentIsCutInsteadOfRejected(): void
  {
    $origin = new RequestOriginResolver($this->stackWith(self::CHROME_ON_WINDOWS . ' ' . str_repeat('x', 2000)))->resolve();

    self::assertSame('Chrome', $origin['browser'] ?? null);
  }

  #[Test]
  public function testItNeverReturnsTheRawUserAgent(): void
  {
    $origin = new RequestOriginResolver($this->stackWith(self::CHROME_ON_WINDOWS . ' <script>alert(1)</script>'))->resolve();

    self::assertNotNull($origin);
    self::assertStringNotContainsString('script', implode(' ', array_filter($origin)));
  }
  // #endregion

  // #region Helpers
  private function stackWith(?string $userAgent): RequestStack
  {
    $request = Request::create('/api/auth/login', 'POST');

    if (null !== $userAgent) {
      $request->headers->set('User-Agent', $userAgent);
    } else {
      $request->headers->remove('User-Agent');
    }

    $stack = new RequestStack();
    $stack->push($request);

    return $stack;
  }
  // #endregion
}
