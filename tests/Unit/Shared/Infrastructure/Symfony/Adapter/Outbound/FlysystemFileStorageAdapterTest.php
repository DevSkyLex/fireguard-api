<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Symfony\Adapter\Outbound;

use League\Flysystem\{Filesystem, FilesystemOperator};
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\{UnableToCheckFileExistence, UnableToDeleteFile, UnableToReadFile, UnableToWriteFile};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Infrastructure\Exception\FileStorageException;
use Shared\Infrastructure\Symfony\Adapter\Outbound\FlysystemFileStorageAdapter;

use function array_diff;
use function is_dir;
use function mkdir;
use function rmdir;
use function scandir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

/**
 * Test FlysystemFileStorageAdapterTest.
 *
 * Unit tests for the FlysystemFileStorageAdapter.
 *
 * @category Unit Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @covers \Shared\Infrastructure\Symfony\Adapter\Outbound\FlysystemFileStorageAdapter
 */
#[CoversClass(className: FlysystemFileStorageAdapter::class)]
final class FlysystemFileStorageAdapterTest extends TestCase
{
  private string $tempDir;

  private FlysystemFileStorageAdapter $adapter;

  /**
   * Set up the test environment with a real local Flysystem filesystem.
   */
  protected function setUp(): void
  {
    $this->tempDir = sys_get_temp_dir() . '/flysystem_storage_test_' . uniqid();
    mkdir($this->tempDir);
    $filesystem = new Filesystem(new LocalFilesystemAdapter($this->tempDir));
    $this->adapter = new FlysystemFileStorageAdapter($filesystem);
  }

  /**
   * Clean up the test environment.
   */
  protected function tearDown(): void
  {
    $this->recursiveRemove($this->tempDir);
  }

  /**
   * Test that a file can be written and read back unchanged.
   */
  #[Test]
  public function testWriteAndRead(): void
  {
    $this->adapter->write('test.txt', 'Hello World');

    $this->assertTrue($this->adapter->exists('test.txt'));
    $this->assertSame('Hello World', $this->adapter->read('test.txt'));
  }

  /**
   * Test that writing a file creates necessary subdirectories, matching
   * the storage key shape used by equipment attachments/avatars/logos.
   */
  #[Test]
  public function testWriteCreatesSubdirectories(): void
  {
    $this->adapter->write('equipment/eq-1/attachments/att-1_photo.jpg', 'binary-data');

    $this->assertTrue($this->adapter->exists('equipment/eq-1/attachments/att-1_photo.jpg'));
    $this->assertSame('binary-data', $this->adapter->read('equipment/eq-1/attachments/att-1_photo.jpg'));
  }

  /**
   * Test that a file can be deleted.
   */
  #[Test]
  public function testDelete(): void
  {
    $this->adapter->write('test.txt', 'content');
    $this->assertTrue($this->adapter->exists('test.txt'));

    $this->adapter->delete('test.txt');

    $this->assertFalse($this->adapter->exists('test.txt'));
  }

  /**
   * Test that deleting a missing file is idempotent (no exception),
   * matching the legacy local-disk FileStorageAdapter behaviour relied on
   * by DeleteAttachmentHandler.
   */
  #[Test]
  public function testDeleteNonExistentFileDoesNotThrow(): void
  {
    $this->expectNotToPerformAssertions();
    $this->adapter->delete('missing.txt');
  }

  /**
   * Test that checking existence of a missing file returns false.
   */
  #[Test]
  public function testExistsReturnsFalseForMissingFile(): void
  {
    $this->assertFalse($this->adapter->exists('missing.txt'));
  }

  /**
   * Test that reading a missing file throws the Shared FileStorageException,
   * as relied upon by GetUserAvatarProvider/GetOrganizationLogoProvider.
   */
  #[Test]
  public function testReadThrowsFileStorageExceptionOnMissingFile(): void
  {
    $this->expectException(FileStorageException::class);
    $this->adapter->read('missing.txt');
  }

  /**
   * Test that a write failure from the underlying Flysystem operator is
   * wrapped into the Shared FileStorageException.
   */
  #[Test]
  public function testWriteWrapsFilesystemExceptionIntoFileStorageException(): void
  {
    $operator = $this->createStub(FilesystemOperator::class);
    $operator->method('write')->willThrowException(UnableToWriteFile::atLocation('test.txt'));

    $adapter = new FlysystemFileStorageAdapter($operator);

    $this->expectException(FileStorageException::class);
    $adapter->write('test.txt', 'content');
  }

  /**
   * Test that a read failure from the underlying Flysystem operator is
   * wrapped into the Shared FileStorageException.
   */
  #[Test]
  public function testReadWrapsFilesystemExceptionIntoFileStorageException(): void
  {
    $operator = $this->createStub(FilesystemOperator::class);
    $operator->method('read')->willThrowException(UnableToReadFile::fromLocation('test.txt'));

    $adapter = new FlysystemFileStorageAdapter($operator);

    $this->expectException(FileStorageException::class);
    $adapter->read('test.txt');
  }

  /**
   * Test that a delete failure from the underlying Flysystem operator is
   * wrapped into the Shared FileStorageException.
   */
  #[Test]
  public function testDeleteWrapsFilesystemExceptionIntoFileStorageException(): void
  {
    $operator = $this->createStub(FilesystemOperator::class);
    $operator->method('delete')->willThrowException(UnableToDeleteFile::atLocation('test.txt'));

    $adapter = new FlysystemFileStorageAdapter($operator);

    $this->expectException(FileStorageException::class);
    $adapter->delete('test.txt');
  }

  /**
   * Test that an existence-check failure from the underlying Flysystem
   * operator is wrapped into the Shared FileStorageException.
   */
  #[Test]
  public function testExistsWrapsFilesystemExceptionIntoFileStorageException(): void
  {
    $operator = $this->createStub(FilesystemOperator::class);
    $operator->method('fileExists')->willThrowException(UnableToCheckFileExistence::forLocation('test.txt'));

    $adapter = new FlysystemFileStorageAdapter($operator);

    $this->expectException(FileStorageException::class);
    $adapter->exists('test.txt');
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
