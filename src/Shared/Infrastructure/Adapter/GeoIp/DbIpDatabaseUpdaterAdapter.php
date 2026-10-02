<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Adapter\GeoIp;

use DateTimeImmutable;
use DateTimeZone;
use MaxMind\Db\Reader;
use Shared\Application\Port\Outbound\ClockPort;
use Shared\Infrastructure\Exception\GeoIpDatabaseException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Lock\{LockFactory, Store\FlockStore};
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

use function chmod;
use function dirname;
use function fclose;
use function file_get_contents;
use function fopen;
use function fwrite;
use function gzclose;
use function gzeof;
use function gzopen;
use function gzread;
use function hash;
use function hash_final;
use function hash_init;
use function hash_update;
use function hexdec;
use function hrtime;
use function is_file;
use function pack;
use function realpath;
use function sprintf;
use function strlen;
use function strtolower;
use function tempnam;

/**
 * Class DbIpDatabaseUpdaterAdapter
 *
 * Bounded, locked download and atomic replacement of the monthly DB-IP Lite database.
 *
 * @category Infrastructure
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DbIpDatabaseUpdaterAdapter
{
  // #region Constants
  /**
   * Constant MAX_ARCHIVE_BYTES
   *
   * Caps compressed payloads at 256 MiB by default.
   *
   * @since 1.0.0
   */
  private const int MAX_ARCHIVE_BYTES = 268435456;

  /**
   * Constant MAX_DATABASE_BYTES
   *
   * Caps expanded payloads at 1 GiB by default.
   *
   * @since 1.0.0
   */
  private const int MAX_DATABASE_BYTES = 1073741824;
  // #endregion

  // #region Constructor
  /**
   * Method __construct
   *
   * Configures bounded maintenance without exposing a download client to request lookups.
   *
   * @access public
   * @since 1.0.0
   *
   * @param HttpClientInterface $client download client, never used by request lookups
   * @param ClockPort $clock UTC release selection
   * @param string $databasePath private persistent database file
   * @param int $maxArchiveBytes compressed download limit
   * @param int $maxDatabaseBytes expanded database limit
   *
   * @return void
   */
  public function __construct(
    private HttpClientInterface $client,
    private ClockPort $clock,
    private string $databasePath,
    private int $maxArchiveBytes = self::MAX_ARCHIVE_BYTES,
    private int $maxDatabaseBytes = self::MAX_DATABASE_BYTES,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method update
   *
   * Serializes monthly replacement and retains the installed file when candidate validation fails.
   *
   * @access public
   * @since 1.0.0
   *
   * @param bool $force replace even when the current month's edition is already installed
   *
   * @return bool whether a new edition was installed
   */
  public function update(bool $force = false): bool
  {
    $filesystem = new Filesystem();
    $directory = dirname($this->databasePath);
    $filesystem->mkdir($directory, 0750);
    // The lock lives on the database volume, shared by concurrent one-shot containers.
    $lock = new LockFactory(new FlockStore($directory))->createLock('dbip-update-' . hash('sha256', $this->databasePath));
    if (!$lock->acquire()) {
      throw new GeoIpDatabaseException('Another GeoIP update is running.');
    }

    $archive = null;
    $expanded = null;

    try {
      $month = $this->clock->now()->setTimezone(new DateTimeZone('UTC'))->format('Y-m');
      if (!$force && is_file($this->databasePath)) {
        try {
          if ($this->databaseBuild()->format('Y-m') === $month) {
            return false;
          }
        } catch (Throwable) {
          // A corrupt installed file must be repairable through the normal update job.
        }
      }

      $archive = $this->temporaryFile($directory);
      $expanded = $this->temporaryFile($directory);
      $this->download($month, $archive);
      $this->expand($archive, $expanded);
      if ($this->inspect($expanded)->format('Y-m') !== $month) {
        throw new GeoIpDatabaseException('The downloaded database does not match the requested release.');
      }

      chmod($expanded, 0640);
      $filesystem->rename($expanded, $this->databasePath, true);
      $expanded = null;

      return true;
    } finally {
      foreach ([$archive, $expanded] as $temporary) {
        if (null !== $temporary) {
          $filesystem->remove($temporary);
        }
      }
      $lock->release();
    }
  }

  /**
   * Method databaseBuild
   *
   * Validates the installed local file without downloading or using request addresses.
   *
   * @access public
   * @since 1.0.0
   *
   * @return DateTimeImmutable validated database build time for operational monitoring
   */
  public function databaseBuild(): DateTimeImmutable
  {
    return $this->inspect($this->databasePath);
  }

  /**
   * Method temporaryFile
   *
   * Allocates candidates on the destination filesystem so publication can remain atomic.
   *
   * @access private
   * @since 1.0.0
   *
   * @param string $directory private directory on the same filesystem as the destination
   *
   * @return string allocated temporary file path
   */
  private function temporaryFile(string $directory): string
  {
    $file = tempnam($directory, '.dbip-');
    if (false === $file || realpath(dirname($file)) !== realpath($directory)) {
      throw new GeoIpDatabaseException('Cannot allocate a GeoIP temporary file.');
    }

    return $file;
  }

  /**
   * Method download
   *
   * Streams only the official monthly HTTPS archive under fixed time and byte limits.
   *
   * @access private
   * @since 1.0.0
   *
   * @param string $month UTC release month
   * @param string $archive allocated gzip file
   *
   * @return void
   */
  private function download(string $month, string $archive): void
  {
    $response = $this->client->request('GET', sprintf('https://download.db-ip.com/free/dbip-city-lite-%s.mmdb.gz', $month), [
      'timeout' => 10,
      'max_duration' => 120,
      'max_redirects' => 0,
      'buffer' => false,
      'headers' => ['Accept-Encoding' => 'identity'],
    ]);
    $handle = null;

    try {
      if (200 !== $response->getStatusCode()) {
        throw new GeoIpDatabaseException('The monthly DB-IP download is unavailable.');
      }

      $handle = fopen($archive, 'wb');
      if (false === $handle) {
        throw new GeoIpDatabaseException('Cannot write the GeoIP archive.');
      }

      $bytes = 0;
      foreach ($this->client->stream($response) as $chunk) {
        $content = $chunk->getContent();
        $bytes += strlen($content);
        if ($bytes > $this->maxArchiveBytes || strlen($content) !== fwrite($handle, $content)) {
          throw new GeoIpDatabaseException('The GeoIP archive exceeds its limit or could not be written.');
        }
      }
    } finally {
      if (null !== $handle && false !== $handle) {
        fclose($handle);
      }
      $response->cancel();
    }
  }

  /**
   * Method expand
   *
   * Streams bounded decompression and verifies archive integrity before publication.
   *
   * @access private
   * @since 1.0.0
   *
   * @param string $archive downloaded gzip archive
   * @param string $expanded allocated database file
   *
   * @return void
   */
  private function expand(string $archive, string $expanded): void
  {
    if ("\x1f\x8b" !== file_get_contents($archive, false, null, 0, 2)) {
      throw new GeoIpDatabaseException('The GeoIP download is not a gzip archive.');
    }

    $input = gzopen($archive, 'rb');
    $output = fopen($expanded, 'wb');
    if (false === $input || false === $output) {
      if (false !== $input) {
        gzclose($input);
      }
      if (false !== $output) {
        fclose($output);
      }

      throw new GeoIpDatabaseException('Cannot expand the GeoIP archive.');
    }

    try {
      $bytes = 0;
      $checksum = hash_init('crc32b');
      $deadline = hrtime(true) + 30000000000;
      while (!gzeof($input)) {
        $content = @gzread($input, 1048576);
        if (false === $content) {
          throw new GeoIpDatabaseException('The GeoIP gzip stream is corrupt.');
        }
        if ('' === $content) {
          break;
        }
        $bytes += strlen($content);
        if (hrtime(true) > $deadline || $bytes > $this->maxDatabaseBytes || strlen($content) !== fwrite($output, $content)) {
          throw new GeoIpDatabaseException('The expanded GeoIP database exceeds its limit or could not be written.');
        }
        hash_update($checksum, $content);
      }
      $this->verifyGzipTrailer($archive, $bytes, hash_final($checksum));
    } finally {
      gzclose($input);
      fclose($output);
    }
  }

  /**
   * Method verifyGzipTrailer
   *
   * Verifies integrity against the streamed payload before the candidate database is published.
   *
   * @access private
   *
   * @param string $archive downloaded gzip archive
   * @param int $bytes expanded payload length in bytes
   * @param string $checksum hexadecimal CRC32 of the expanded payload
   *
   * @return void
   */
  private function verifyGzipTrailer(string $archive, int $bytes, string $checksum): void
  {
    $trailer = file_get_contents($archive, false, null, -8);
    $expected = pack('V', (int) hexdec($checksum)) . pack('V', $bytes);
    if ($trailer !== $expected) {
      throw new GeoIpDatabaseException('The GeoIP gzip checksum or length is invalid.');
    }
  }

  /**
   * Method inspect
   *
   * Checks local metadata and both address-family search paths before accepting a database.
   *
   * @access private
   * @since 1.0.0
   *
   * @param string $path candidate MMDB file
   *
   * @return DateTimeImmutable verified DB-IP City Lite build time
   */
  private function inspect(string $path): DateTimeImmutable
  {
    $reader = new Reader($path);

    try {
      $metadata = $reader->metadata();
      if ('dbip-city-lite' !== strtolower($metadata->databaseType) || 6 !== $metadata->ipVersion || $metadata->buildEpoch > $this->clock->now()->getTimestamp()) {
        throw new GeoIpDatabaseException('Unexpected GeoIP database metadata.');
      }
      // Exercise both search paths before publishing; no user IP participates in validation.
      $reader->get('8.8.8.8');
      $reader->get('2001:4860:4860::8888');

      return new DateTimeImmutable('@' . $metadata->buildEpoch);
    } finally {
      $reader->close();
    }
  }
  // #endregion
}
