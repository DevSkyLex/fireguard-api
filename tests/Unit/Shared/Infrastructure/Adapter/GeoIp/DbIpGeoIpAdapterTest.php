<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Adapter\GeoIp;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\ClockPort;
use Shared\Infrastructure\Adapter\GeoIp\DbIpGeoIpAdapter;
use Tests\Support\GeoIp\SyntheticMmdb;

use function file_put_contents;
use function is_file;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

/**
 * Local lookup behavior against a synthetic database, independent of network and real IP data.
 *
 * @category Unit Tests
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(DbIpGeoIpAdapter::class)]
final class DbIpGeoIpAdapterTest extends TestCase
{
  // #region Properties
  /**
   * @var string fixture path
   */
  private string $path;
  // #endregion

  // #region Methods
  protected function setUp(): void
  {
    $path = tempnam(sys_get_temp_dir(), 'fireguard-mmdb-');
    self::assertNotFalse($path);
    $this->path = $path;
    file_put_contents($this->path, SyntheticMmdb::bytes(new DateTimeImmutable('2026-10-01T00:00:00Z')));
  }

  protected function tearDown(): void
  {
    if (is_file($this->path)) {
      unlink($this->path);
    }
  }

  #[Test]
  public function testReadsIpv4AndIpv6AndReopensAfterReplacement(): void
  {
    $adapter = $this->adapter();
    foreach (['8.8.8.8', '2001:4860:4860::8888'] as $ip) {
      $location = $adapter->locate($ip);
      self::assertNotNull($location);
      self::assertSame('FR', $location->countryCode);
      self::assertSame('Paris', $location->city);
    }
    file_put_contents($this->path, SyntheticMmdb::bytes(new DateTimeImmutable('2026-10-01T00:00:00Z'), ['country' => ['iso_code' => 'ES']]));
    $updated = $adapter->locate('8.8.8.8');
    self::assertNotNull($updated);
    self::assertSame('ES', $updated->countryCode);
    self::assertNull($updated->city);
  }

  #[Test]
  #[DataProvider('nonPublicIps')]
  public function testRejectsNonPublicIps(string $ip): void
  {
    self::assertNull($this->adapter()->locate($ip));
  }

  /**
   * @return iterable<string, array{string}> non-public or invalid addresses
   */
  public static function nonPublicIps(): iterable
  {
    foreach (['', 'invalid', '127.0.0.1', '10.1.2.3', '192.168.1.1', '192.0.2.1', '0.0.0.0', '::1', 'fc00::1', 'fe80::1', '::ffff:127.0.0.1'] as $ip) {
      yield $ip => [$ip];
    }
  }

  #[Test]
  public function testDisabledMissingCorruptStaleAndFutureDatabasesAreOptional(): void
  {
    self::assertNull($this->adapter(false)->locate('8.8.8.8'));
    foreach (['broken', SyntheticMmdb::bytes(new DateTimeImmutable('2026-08-01T00:00:00Z')), SyntheticMmdb::bytes(new DateTimeImmutable('2026-11-01T00:00:00Z'))] as $bytes) {
      file_put_contents($this->path, $bytes);
      self::assertNull($this->adapter()->locate('8.8.8.8'));
    }
    unlink($this->path);
    self::assertNull($this->adapter()->locate('8.8.8.8'));
  }

  #[Test]
  public function testUnmappedAndInvalidCountryReturnNoLocation(): void
  {
    file_put_contents($this->path, SyntheticMmdb::bytes(new DateTimeImmutable('2026-10-01T00:00:00Z'), mapped: false));
    self::assertNull($this->adapter()->locate('8.8.8.8'));
    file_put_contents($this->path, SyntheticMmdb::bytes(new DateTimeImmutable('2026-10-01T00:00:00Z'), ['country' => ['iso_code' => 'ZZ']]));
    self::assertNull($this->adapter()->locate('8.8.8.8'));
  }

  #[Test]
  public function testInvalidCityTextIsDiscardedAndHtmlRemainsPlainText(): void
  {
    foreach (["Paris\n" => 'Paris', "Pa\0ris" => null, "\xFF" => null, '<script>alert(1)</script>' => '<script>alert(1)</script>'] as $city => $expected) {
      file_put_contents($this->path, SyntheticMmdb::bytes(new DateTimeImmutable('2026-10-01T00:00:00Z'), ['country' => ['iso_code' => 'FR'], 'city' => ['names' => ['en' => $city]]]));
      self::assertSame($expected, $this->adapter()->locate('8.8.8.8')?->city);
    }
  }

  /**
   * @param bool $enabled collection switch @return DbIpGeoIpAdapter lookup under a fixed clock
   */
  private function adapter(bool $enabled = true): DbIpGeoIpAdapter
  {
    $clock = $this->createStub(ClockPort::class);
    $clock->method('now')->willReturn(new DateTimeImmutable('2026-10-01T12:00:00Z'));

    return new DbIpGeoIpAdapter($this->path, $enabled, 45, $clock);
  }
  // #endregion
}
