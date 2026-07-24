<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Storage;

use AsyncAws\S3\S3Client;
use InvalidArgumentException;
use League\Flysystem\AsyncAwsS3\AsyncAwsS3Adapter;
use League\Flysystem\{Filesystem, FilesystemOperator};
use League\Flysystem\Local\LocalFilesystemAdapter;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Shared\Infrastructure\Storage\FlysystemFactory;

use function array_diff;
use function is_dir;
use function mkdir;
use function rawurlencode;
use function rmdir;
use function scandir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

use const DIRECTORY_SEPARATOR;

/**
 * Test FlysystemFactoryTest.
 *
 * Unit tests for the FlysystemFactory DSN parser.
 *
 * @category Unit Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @covers \Shared\Infrastructure\Storage\FlysystemFactory
 */
#[CoversClass(className: FlysystemFactory::class)]
final class FlysystemFactoryTest extends TestCase
{
  private string $projectDir;

  /**
   * Set up a fake project root for relative `local://` resolution.
   */
  protected function setUp(): void
  {
    $this->projectDir = sys_get_temp_dir() . '/flysystem_factory_test_' . uniqid();
    mkdir($this->projectDir, 0775, true);
  }

  /**
   * Clean up the fake project root.
   */
  protected function tearDown(): void
  {
    $this->recursiveRemove($this->projectDir);
  }

  /**
   * Test that a `local://` DSN with a relative path resolves against the
   * project root, matching the legacy `shared.file_storage.base_path`
   * (`%kernel.project_dir%/var/storage`) location.
   */
  #[Test]
  public function testLocalSchemeWithRelativePathResolvesAgainstProjectDir(): void
  {
    $factory = new FlysystemFactory($this->projectDir);

    $operator = $factory->create('local://var/storage');
    $operator->write('probe.txt', 'content');

    $this->assertFileExists($this->projectDir . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'probe.txt');
    $this->assertSame('content', $operator->read('probe.txt'));
  }

  /**
   * Test that a `local://` DSN with an absolute path is used as-is,
   * ignoring the project root.
   */
  #[Test]
  public function testLocalSchemeWithAbsolutePathIsUsedAsIs(): void
  {
    $absoluteTarget = sys_get_temp_dir() . '/flysystem_factory_abs_' . uniqid();
    mkdir($absoluteTarget, 0775, true);

    try {
      $factory = new FlysystemFactory('/should/not/be/used');

      $operator = $factory->create('local://' . $absoluteTarget);
      $operator->write('probe.txt', 'content');

      $this->assertFileExists($absoluteTarget . DIRECTORY_SEPARATOR . 'probe.txt');
    } finally {
      $this->recursiveRemove($absoluteTarget);
    }
  }

  /**
   * Test that the returned local operator is a Flysystem Filesystem backed
   * by the LocalFilesystemAdapter.
   */
  #[Test]
  public function testLocalSchemeBuildsLocalFilesystemAdapter(): void
  {
    $factory = new FlysystemFactory($this->projectDir);

    $operator = $factory->create('local://var/storage');

    $this->assertInstanceOf(Filesystem::class, $operator);
    $this->assertInstanceOf(LocalFilesystemAdapter::class, $this->extractAdapter($operator));
  }

  /**
   * Test that an empty `local://` path is rejected.
   */
  #[Test]
  public function testLocalSchemeWithEmptyPathThrows(): void
  {
    $factory = new FlysystemFactory($this->projectDir);

    $this->expectException(InvalidArgumentException::class);
    $factory->create('local://');
  }

  /**
   * Test that a DSN without any recognizable scheme is rejected.
   */
  #[Test]
  public function testDsnWithoutSchemeThrows(): void
  {
    $factory = new FlysystemFactory($this->projectDir);

    $this->expectException(InvalidArgumentException::class);
    $factory->create('not-a-dsn');
  }

  /**
   * Test that an unsupported scheme is rejected.
   */
  #[Test]
  public function testDsnWithUnsupportedSchemeThrows(): void
  {
    $factory = new FlysystemFactory($this->projectDir);

    $this->expectException(InvalidArgumentException::class);
    $factory->create('ftp://host/path');
  }

  /**
   * Test that a minimal `s3://` DSN builds an AsyncAws S3 adapter with the
   * expected bucket, region and credentials.
   */
  #[Test]
  public function testS3SchemeBuildsAsyncAwsAdapter(): void
  {
    $factory = new FlysystemFactory($this->projectDir);

    $operator = $factory->create('s3://AKIAKEY:secretvalue@my-bucket?region=eu-west-1');

    $this->assertInstanceOf(Filesystem::class, $operator);
    $adapter = $this->extractS3Adapter($operator);

    $this->assertSame('my-bucket', $this->extractS3Bucket($adapter));

    $client = $this->extractS3Client($adapter);
    $configuration = $client->getConfiguration();
    $this->assertSame('eu-west-1', $configuration->get('region'));
    $this->assertSame('AKIAKEY', $configuration->get('accessKeyId'));
    $this->assertSame('secretvalue', $configuration->get('accessKeySecret'));
    // No "endpoint" query parameter was provided: async-aws falls back to
    // its own AWS endpoint template, proving no custom endpoint leaked in.
    $this->assertSame('https://%service%.%region%.amazonaws.com', $configuration->get('endpoint'));
  }

  /**
   * Test that MinIO-style query parameters (URL-encoded endpoint and
   * path-style addressing) are forwarded to the S3 client configuration.
   */
  #[Test]
  public function testS3SchemeWithMinioEndpointAndPathStyle(): void
  {
    $factory = new FlysystemFactory($this->projectDir);

    $dsn = 's3://minioadmin:minioadmin@fireguard-storage'
      . '?region=us-east-1&endpoint=' . rawurlencode('http://localhost:9000')
      . '&use_path_style_endpoint=1';

    $operator = $factory->create($dsn);

    $adapter = $this->extractS3Adapter($operator);
    $client = $this->extractS3Client($adapter);
    $configuration = $client->getConfiguration();

    $this->assertSame('http://localhost:9000', $configuration->get('endpoint'));
    $this->assertSame('1', $configuration->get('pathStyleEndpoint'));
  }

  /**
   * Test that credentials containing percent-encoded characters are
   * decoded before being forwarded to the S3 client.
   */
  #[Test]
  public function testS3SchemeDecodesPercentEncodedCredentials(): void
  {
    $factory = new FlysystemFactory($this->projectDir);

    $operator = $factory->create('s3://AKIA%2FKEY:sec%2Fret@my-bucket?region=eu-west-1');

    $adapter = $this->extractS3Adapter($operator);
    $client = $this->extractS3Client($adapter);
    $configuration = $client->getConfiguration();

    $this->assertSame('AKIA/KEY', $configuration->get('accessKeyId'));
    $this->assertSame('sec/ret', $configuration->get('accessKeySecret'));
  }

  /**
   * Test that a malformed `s3://` DSN (no bucket) is rejected.
   */
  #[Test]
  public function testS3SchemeWithoutBucketThrows(): void
  {
    $factory = new FlysystemFactory($this->projectDir);

    $this->expectException(InvalidArgumentException::class);
    $factory->create('s3://');
  }

  /**
   * Test that an `s3://` DSN missing credentials is rejected.
   */
  #[Test]
  public function testS3SchemeWithoutCredentialsThrows(): void
  {
    $factory = new FlysystemFactory($this->projectDir);

    $this->expectException(InvalidArgumentException::class);
    $factory->create('s3://my-bucket?region=eu-west-1');
  }

  /**
   * Test that an `s3://` DSN missing the "region" query parameter is
   * rejected.
   */
  #[Test]
  public function testS3SchemeWithoutRegionThrows(): void
  {
    $factory = new FlysystemFactory($this->projectDir);

    $this->expectException(InvalidArgumentException::class);
    $factory->create('s3://key:secret@my-bucket');
  }

  /**
   * Extracts the private FilesystemAdapter wrapped by a Flysystem operator.
   */
  private function extractAdapter(FilesystemOperator $filesystem): object
  {
    $property = new ReflectionClass($filesystem)->getProperty('adapter');

    /** @var object $adapter */
    $adapter = $property->getValue($filesystem);

    return $adapter;
  }

  /**
   * Extracts the AsyncAwsS3Adapter wrapped by a Flysystem operator.
   */
  private function extractS3Adapter(FilesystemOperator $filesystem): AsyncAwsS3Adapter
  {
    $adapter = $this->extractAdapter($filesystem);

    self::assertInstanceOf(AsyncAwsS3Adapter::class, $adapter);

    return $adapter;
  }

  /**
   * Extracts the S3Client wrapped by an AsyncAwsS3Adapter.
   */
  private function extractS3Client(AsyncAwsS3Adapter $adapter): S3Client
  {
    $property = new ReflectionClass($adapter)->getProperty('client');
    $client = $property->getValue($adapter);

    self::assertInstanceOf(S3Client::class, $client);

    return $client;
  }

  /**
   * Extracts the bucket name off an AsyncAwsS3Adapter.
   */
  private function extractS3Bucket(AsyncAwsS3Adapter $adapter): string
  {
    $property = new ReflectionClass($adapter)->getProperty('bucket');
    $bucket = $property->getValue($adapter);

    self::assertIsString($bucket);

    return $bucket;
  }

  /**
   * Recursively remove a directory and its contents.
   *
   * @param string $dir the directory to remove
   */
  private function recursiveRemove(string $dir): void
  {
    if (!is_dir($dir)) {
      return;
    }
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
      (is_dir("$dir/$file")) ? $this->recursiveRemove("$dir/$file") : unlink("$dir/$file");
    }
    rmdir($dir);
  }
}
