<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Infrastructure\Adapter\Cache;

use OAuth\Infrastructure\Adapter\Cache\TokenCacheAdapter;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * Test TokenCacheAdapterTest.
 *
 * @category Adapter Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(TokenCacheAdapter::class)]
final class TokenCacheAdapterTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testGetReturnsNullWhenMissing(): void
  {
    $adapter = new TokenCacheAdapter(cache: new ArrayAdapter(), ttl: 60);

    self::assertNull($adapter->get('token-1'));
  }

  #[Test]
  public function testSetAndGet(): void
  {
    $adapter = new TokenCacheAdapter(cache: new ArrayAdapter(), ttl: 60);

    $adapter->set('token-1', ['active' => true]);

    self::assertSame(['active' => true], $adapter->get('token-1'));
  }

  #[Test]
  public function testInvalidateRemovesItem(): void
  {
    $adapter = new TokenCacheAdapter(cache: new ArrayAdapter(), ttl: 60);

    $adapter->set('token-1', ['active' => true]);
    $adapter->invalidate('token-1');

    self::assertNull($adapter->get('token-1'));
  }
  // #endregion
}
