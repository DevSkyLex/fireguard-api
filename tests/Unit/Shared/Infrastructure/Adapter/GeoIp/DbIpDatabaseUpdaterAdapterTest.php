<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Adapter\GeoIp;

use DateTimeImmutable;
use Generator;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Port\Outbound\ClockPort;
use Shared\Infrastructure\Adapter\GeoIp\DbIpDatabaseUpdaterAdapter;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\{MockHttpClient, Response\MockResponse};
use Symfony\Component\Lock\{LockFactory, Store\FlockStore};
use Tests\Support\GeoIp\SyntheticMmdb;
use Throwable;

use function bin2hex;
use function file_get_contents;
use function file_put_contents;
use function glob;
use function gzencode;
use function hash;
use function mkdir;
use function random_bytes;
use function str_replace;
use function substr;
use function sys_get_temp_dir;

/**
 * Exercises real MMDB validation with deterministic synthetic files and streamed HTTP doubles.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class DbIpDatabaseUpdaterAdapterTest extends TestCase
{
  private string $directory;

  private string $path;

  private ClockPort $clock;

  protected function setUp(): void
  {
    $this->directory = sys_get_temp_dir() . '/fireguard-geoip-update-' . bin2hex(random_bytes(6));
    mkdir($this->directory);
    $this->path = $this->directory . '/city.mmdb';
    $this->clock = $this->createStub(ClockPort::class);
    $this->clock->method('now')->willReturn(new DateTimeImmutable('2026-10-01T12:00:00Z'));
  }

  protected function tearDown(): void
  {
    new Filesystem()->remove($this->directory);
  }

  public function testPublishesValidStreamAndSkipsInstalledMonth(): void
  {
    $bytes = SyntheticMmdb::bytes(new DateTimeImmutable('2026-10-01T00:00:00Z'));
    $client = new MockHttpClient(function (string $method, string $url, array $options) use ($bytes): MockResponse {
      self::assertSame('GET', $method);
      self::assertSame('https://download.db-ip.com/free/dbip-city-lite-2026-10.mmdb.gz', $url);
      self::assertSame(0, $options['max_redirects']);
      self::assertSame(120.0, $options['max_duration']);

      return new MockResponse($this->gzip($bytes));
    });
    $updater = new DbIpDatabaseUpdaterAdapter($client, $this->clock, $this->path);
    self::assertTrue($updater->update());
    self::assertSame($bytes, file_get_contents($this->path));
    self::assertFalse($updater->update());
    self::assertSame(1, $client->getRequestsCount());
  }

  public function testFailedDownloadsKeepPreviousDatabaseAndRemoveTemporaryFiles(): void
  {
    $previous = SyntheticMmdb::bytes(new DateTimeImmutable('2026-09-01T00:00:00Z'));
    $wrongMonth = $this->gzip($previous);
    $current = SyntheticMmdb::bytes(new DateTimeImmutable('2026-10-01T00:00:00Z'));
    $interrupted = static function (): Generator {
      yield 'start';

      throw new RuntimeException('Interrupted download');
    };
    foreach ([
      new MockResponse('unavailable', ['http_code' => 503]),
      new MockResponse('not a gzip archive'),
      new MockResponse($this->gzip('not a MMDB')),
      new MockResponse(substr($this->gzip($current), 0, -4)),
      new MockResponse($wrongMonth),
      new MockResponse($interrupted()),
      new MockResponse($this->gzip(str_replace('DBIP-City-Lite', 'Unexpected-db', $current))),
    ] as $response) {
      file_put_contents($this->path, $previous);
      $updater = new DbIpDatabaseUpdaterAdapter(new MockHttpClient($response), $this->clock, $this->path);

      try {
        $updater->update();
        self::fail('Invalid or interrupted downloads must fail.');
      } catch (Throwable $error) {
        self::assertNotInstanceOf(\PHPUnit\Framework\AssertionFailedError::class, $error);
      }
      self::assertSame($previous, file_get_contents($this->path));
      self::assertSame([], glob($this->directory . '/.dbip-*'));
    }
  }

  public function testConfiguredArchiveAndExpandedLimitsPreserveInstalledDatabase(): void
  {
    $previous = SyntheticMmdb::bytes(new DateTimeImmutable('2026-09-01T00:00:00Z'));
    $current = $this->gzip(SyntheticMmdb::bytes(new DateTimeImmutable('2026-10-01T00:00:00Z')));
    foreach ([[10, 1048576], [1048576, 10]] as [$archiveLimit, $expandedLimit]) {
      file_put_contents($this->path, $previous);

      try {
        new DbIpDatabaseUpdaterAdapter(new MockHttpClient(new MockResponse($current)), $this->clock, $this->path, $archiveLimit, $expandedLimit)->update();
        self::fail('Size limit must stop publishing.');
      } catch (RuntimeException) {
        self::assertSame($previous, file_get_contents($this->path));
      }
    }
  }

  public function testConcurrentUpdaterCannotDownloadOrReplaceFile(): void
  {
    $lock = new LockFactory(new FlockStore($this->directory))->createLock('dbip-update-' . hash('sha256', $this->path));
    self::assertTrue($lock->acquire());
    $client = new MockHttpClient();

    try {
      $this->expectException(RuntimeException::class);
      new DbIpDatabaseUpdaterAdapter($client, $this->clock, $this->path)->update();
    } finally {
      $lock->release();
      self::assertSame(0, $client->getRequestsCount());
    }
  }

  private function gzip(string $value): string
  {
    $result = gzencode($value);
    self::assertIsString($result);

    return $result;
  }
}
