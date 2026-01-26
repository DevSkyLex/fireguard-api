<?php

declare(strict_types=1);

namespace Tests\Unit\App;

use App\Kernel;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

use function str_replace;

/**
 * Test KernelTest.
 *
 * @category Unit Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(Kernel::class)]
final class KernelTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testGetCacheAndLogDirUseOverrides(): void
  {
    $originalServer = $_SERVER;
    $originalEnv = $_ENV;

    try {
      $_SERVER['APP_CACHE_DIR'] = $_ENV['APP_CACHE_DIR'] = 'C:/tmp/fireguard/cache';
      $_SERVER['APP_LOG_DIR'] = $_ENV['APP_LOG_DIR'] = 'C:/tmp/fireguard/log';

      $kernel = new Kernel('test', true);

      self::assertSame('C:/tmp/fireguard/cache', $kernel->getCacheDir());
      self::assertSame('C:/tmp/fireguard/log', $kernel->getLogDir());
    } finally {
      $_SERVER = $originalServer;
      $_ENV = $originalEnv;
    }
  }

  #[Test]
  public function testGetCacheAndLogDirUseDefaults(): void
  {
    $originalServer = $_SERVER;
    $originalEnv = $_ENV;

    try {
      unset($_SERVER['APP_CACHE_DIR'], $_ENV['APP_CACHE_DIR'], $_SERVER['APP_LOG_DIR'], $_ENV['APP_LOG_DIR']);

      $kernel = new Kernel('test', true);

      $cacheDir = str_replace('\\', '/', $kernel->getCacheDir());
      $logDir = str_replace('\\', '/', $kernel->getLogDir());

      self::assertStringContainsString('var/cache/test', $cacheDir);
      self::assertStringContainsString('var/log', $logDir);
    } finally {
      $_SERVER = $originalServer;
      $_ENV = $originalEnv;
    }
  }
  // #endregion
}
