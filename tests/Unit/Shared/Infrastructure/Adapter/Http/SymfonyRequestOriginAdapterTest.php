<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Adapter\Http;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Infrastructure\Adapter\Http\SymfonyRequestOriginAdapter;
use Symfony\Component\HttpFoundation\{Request, RequestStack};

use function str_repeat;

/**
 * Client IP extraction depends on explicit trusted proxies, never arbitrary forwarded headers.
 *
 * @category Unit Tests
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(SymfonyRequestOriginAdapter::class)]
final class SymfonyRequestOriginAdapterTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testHasNoContextOutsideHttpAndKeepsIpWithoutKnownBrowser(): void
  {
    $stack = new RequestStack();
    $adapter = new SymfonyRequestOriginAdapter($stack);
    self::assertNull($adapter->current());
    $stack->push(Request::create('/', server: ['REMOTE_ADDR' => '8.8.8.8', 'HTTP_USER_AGENT' => 'unknown']));
    $current = new SymfonyRequestOriginAdapter($stack)->current();
    self::assertNotNull($current);
    self::assertSame('8.8.8.8', $current->ipAddress);
    self::assertNull($current->browser);
  }

  #[Test]
  public function testUnknownEmptyAndOversizedHeadersPreserveIpAndLocale(): void
  {
    foreach (['0', ' 0 ', '', '   ', str_repeat('x', 2000)] as $header) {
      $request = Request::create('/', server: ['REMOTE_ADDR' => '8.8.8.8', 'HTTP_USER_AGENT' => $header]);
      $request->setLocale('fr');
      $stack = new RequestStack();
      $stack->push($request);
      $origin = new SymfonyRequestOriginAdapter($stack)->current();
      self::assertNotNull($origin);
      self::assertSame('8.8.8.8', $origin->ipAddress);
      self::assertSame('fr', $origin->locale);
      self::assertNull($origin->browser);
      self::assertNull($origin->operatingSystem);
    }
  }

  #[Test]
  public function testOnlyTrustedProxyCanSupplyClientIp(): void
  {
    $previous = Request::getTrustedProxies();
    /** @var int<0, 63> $headers */
    $headers = Request::getTrustedHeaderSet();

    try {
      Request::setTrustedProxies(['10.0.0.2'], Request::HEADER_X_FORWARDED_FOR);
      $stack = new RequestStack();
      $adapter = new SymfonyRequestOriginAdapter($stack);
      $request = Request::create('/', server: ['REMOTE_ADDR' => '9.9.9.9', 'HTTP_X_FORWARDED_FOR' => '8.8.8.8']);
      $stack->push($request);
      $direct = $adapter->current();
      self::assertNotNull($direct);
      self::assertSame('9.9.9.9', $direct->ipAddress);
      $stack->pop();
      $request = Request::create('/', server: ['REMOTE_ADDR' => '10.0.0.2', 'HTTP_X_FORWARDED_FOR' => '8.8.8.8']);
      $stack->push($request);
      $forwarded = new SymfonyRequestOriginAdapter($stack)->current();
      self::assertNotNull($forwarded);
      self::assertSame('8.8.8.8', $forwarded->ipAddress);
    } finally {
      Request::setTrustedProxies($previous, $headers);
    }
  }
  // #endregion
}
