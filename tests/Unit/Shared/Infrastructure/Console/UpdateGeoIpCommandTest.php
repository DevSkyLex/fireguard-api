<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Console;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\ClockPort;
use Shared\Infrastructure\Adapter\GeoIp\DbIpDatabaseUpdaterAdapter;
use Shared\Infrastructure\Console\UpdateGeoIpCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\{MockHttpClient, Response\MockResponse};
use Tests\Support\GeoIp\SyntheticMmdb;

use function bin2hex;
use function file_get_contents;
use function file_put_contents;
use function glob;
use function gzencode;
use function mkdir;
use function random_bytes;
use function sys_get_temp_dir;

/**
 * Class UpdateGeoIpCommandTest
 *
 * Covers maintenance exit codes, local checks and fixed operational output using synthetic files.
 *
 * @category Test
 */
#[CoversClass(UpdateGeoIpCommand::class)]
final class UpdateGeoIpCommandTest extends TestCase
{
  // #region Properties
  private string $directory;

  private string $path;

  private ClockPort $clock;
  // #endregion

  // #region Methods
  protected function setUp(): void
  {
    $this->directory = sys_get_temp_dir() . '/fireguard-geoip-command-' . bin2hex(random_bytes(6));
    mkdir($this->directory);
    $this->path = $this->directory . '/city.mmdb';
    $this->clock = $this->createStub(ClockPort::class);
    $this->clock->method('now')->willReturn(new DateTimeImmutable('2026-10-01T12:00:00Z'));
  }

  protected function tearDown(): void
  {
    new Filesystem()->remove($this->directory);
  }

  public function testDisabledScheduledMaintenanceSkipsMissingFileAndDownload(): void
  {
    $client = $this->offlineClient();
    $tester = $this->tester($client, false);

    self::assertSame(Command::SUCCESS, $tester->execute(['--if-enabled' => true, '--check' => true]));
    self::assertStringContainsString('GeoIP collection is disabled; maintenance skipped.', $tester->getDisplay());
    self::assertSame(0, $client->getRequestsCount());
  }

  #[DataProvider('databaseChecks')]
  public function testChecksInstalledDatabaseWithoutDownloading(string $build, int $maxAgeDays, int $expectedStatus, string $expectedText): void
  {
    file_put_contents($this->path, SyntheticMmdb::bytes(new DateTimeImmutable($build)));
    $client = $this->offlineClient();
    $tester = $this->tester($client, maxAgeDays: $maxAgeDays);

    self::assertSame($expectedStatus, $tester->execute(['--check' => true]));
    self::assertStringContainsString($expectedText, $tester->getDisplay());
    self::assertSame(0, $client->getRequestsCount());
  }

  /**
   * Method databaseChecks
   *
   * Distinguishes current, boundary-age, stale, disabled-window and future candidate files.
   *
   * @access public
   *
   * @return iterable<string, array{string, int, int, string}> build, age limit and expected output
   */
  public static function databaseChecks(): iterable
  {
    yield 'current' => ['2026-10-01T00:00:00Z', 45, Command::SUCCESS, 'GeoIP database is valid; build: 2026-10-01 00:00:00 UTC.'];
    yield 'age boundary' => ['2026-08-17T12:00:00Z', 45, Command::SUCCESS, 'GeoIP database is valid; build: 2026-08-17 12:00:00 UTC.'];
    yield 'stale' => ['2026-08-17T11:59:59Z', 45, Command::FAILURE, 'The GeoIP database is stale; new enrichments are suspended.'];
    yield 'disabled age window' => ['2026-10-01T00:00:00Z', 0, Command::FAILURE, 'The GeoIP database is stale; new enrichments are suspended.'];
    yield 'future' => ['2026-10-02T00:00:00Z', 45, Command::FAILURE, 'GeoIP maintenance failed.'];
  }

  public function testUpdatesThenReportsCurrentEditionWithoutAnotherDownload(): void
  {
    $database = SyntheticMmdb::bytes(new DateTimeImmutable('2026-10-01T00:00:00Z'));
    $archive = gzencode($database);
    self::assertIsString($archive);
    $client = new MockHttpClient(new MockResponse($archive));
    $tester = $this->tester($client);

    self::assertSame(Command::SUCCESS, $tester->execute([]));
    self::assertStringContainsString('GeoIP database updated.', $tester->getDisplay());
    self::assertSame($database, file_get_contents($this->path));
    self::assertSame(Command::SUCCESS, $tester->execute([]));
    self::assertStringContainsString('The current monthly GeoIP edition is already installed.', $tester->getDisplay());
    self::assertSame(1, $client->getRequestsCount());
  }

  public function testFailedDownloadRetainsInstalledDatabaseAndRedactsProviderResponse(): void
  {
    $previous = SyntheticMmdb::bytes(new DateTimeImmutable('2026-09-01T00:00:00Z'));
    file_put_contents($this->path, $previous);
    $client = new MockHttpClient(new MockResponse('provider-private-response', ['http_code' => 503]));
    $tester = $this->tester($client);

    self::assertSame(Command::FAILURE, $tester->execute([]));
    self::assertStringContainsString('GeoIP maintenance failed. The last installed database was retained;', $tester->getDisplay());
    self::assertStringNotContainsString('provider-private-response', $tester->getDisplay());
    self::assertStringNotContainsString($this->path, $tester->getDisplay());
    self::assertSame($previous, file_get_contents($this->path));
    self::assertSame([], glob($this->directory . '/.dbip-*'));
    self::assertSame(1, $client->getRequestsCount());
  }

  /**
   * Method offlineClient
   *
   * Makes unintended network use fail during local maintenance checks.
   *
   * @access private
   *
   * @return MockHttpClient client with no allowed requests
   */
  private function offlineClient(): MockHttpClient
  {
    return new MockHttpClient(static function (): never {
      self::fail('Local maintenance checks must never send a network request.');
    });
  }

  /**
   * Method tester
   *
   * Executes the real updater with a fixed clock and a private test-only destination.
   *
   * @access private
   *
   * @param MockHttpClient $client controlled download transport
   * @param bool $enabled collection switch
   * @param int $maxAgeDays maximum usable build age
   *
   * @return CommandTester real maintenance command harness
   */
  private function tester(MockHttpClient $client, bool $enabled = true, int $maxAgeDays = 45): CommandTester
  {
    $updater = new DbIpDatabaseUpdaterAdapter($client, $this->clock, $this->path);

    return new CommandTester(new UpdateGeoIpCommand($updater, $this->clock, $enabled, $maxAgeDays));
  }
  // #endregion
}
