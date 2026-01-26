<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Symfony\Adapter\Outbound;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Infrastructure\Exception\FileStorageException;
use Shared\Infrastructure\Symfony\Adapter\Outbound\FileStorageAdapter;

use function array_diff;
use function file_put_contents;
use function is_dir;
use function mkdir;
use function restore_error_handler;
use function rmdir;
use function scandir;
use function set_error_handler;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

/**
 * Test FileStorageAdapterTest.
 *
 * Unit tests for the FileStorageAdapter.
 *
 * @category Unit Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @covers \Shared\Infrastructure\Symfony\Adapter\Outbound\FileStorageAdapter
 */
#[CoversClass(className: FileStorageAdapter::class)]
final class FileStorageAdapterTest extends TestCase
{
  private string $tempDir;

  private FileStorageAdapter $adapter;

  /**
   * Set up the test environment.
   */
  protected function setUp(): void
  {
    $this->tempDir = sys_get_temp_dir() . '/file_storage_test_' . uniqid();
    mkdir($this->tempDir);
    $this->adapter = new FileStorageAdapter($this->tempDir);
  }

  /**
   * Clean up the test environment.
   */
  protected function tearDown(): void
  {
    $this->recursiveRemove($this->tempDir);
  }

  /**
   * Test that a file can be written and read.
   */
  #[Test]
  public function testWriteAndRead(): void
  {
    $path = 'test.txt';
    $content = 'Hello World';

    $this->adapter->write($path, $content);
    $this->assertTrue($this->adapter->exists($path));
    $this->assertEquals($content, $this->adapter->read($path));
  }

  /**
   * Test that writing a file creates necessary subdirectories.
   */
  #[Test]
  public function testWriteCreatesDirectories(): void
  {
    $path = 'subdir/test.txt';
    $content = 'Hello Subdir';

    $this->adapter->write($path, $content);
    $this->assertTrue($this->adapter->exists($path));
    $this->assertEquals($content, $this->adapter->read($path));
  }

  /**
   * Test that reading a non-existent file throws an exception.
   */
  #[Test]
  public function testReadThrowsExceptionIfFileNotFound(): void
  {
    $this->expectException(FileStorageException::class);
    $this->adapter->read('non_existent.txt');
  }

  /**
   * Test that a file can be deleted.
   */
  #[Test]
  public function testDelete(): void
  {
    $path = 'test.txt';
    $this->adapter->write($path, 'content');
    $this->assertTrue($this->adapter->exists($path));

    $this->adapter->delete($path);
    $this->assertFalse($this->adapter->exists($path));
  }

  /**
   * Test that deleting a non-existent file does not throw an exception.
   */
  #[Test]
  public function testDeleteNonExistentFileDoesNotThrow(): void
  {
    $this->expectNotToPerformAssertions();
    $this->adapter->delete('non_existent.txt');
  }

  #[Test]
  public function testWriteThrowsWhenDirectoryCreationFails(): void
  {
    $baseFile = $this->tempDir . '/base.txt';
    file_put_contents($baseFile, 'base');

    $this->expectException(FileStorageException::class);

    $adapter = new FileStorageAdapter($baseFile);
    $adapter->write('nested/test.txt', 'content');
  }

  #[Test]
  public function testWriteThrowsWhenFilePutFails(): void
  {
    $dirPath = $this->tempDir . '/test.txt';
    mkdir($dirPath);

    $this->expectException(FileStorageException::class);

    $this->suppressWarnings(function (): void {
      $this->adapter->write('test.txt', 'content');
    });
  }

  #[Test]
  public function testDeleteThrowsWhenUnlinkFails(): void
  {
    $dirPath = $this->tempDir . '/locked.txt';
    mkdir($dirPath);

    $this->expectException(FileStorageException::class);

    $this->suppressWarnings(function (): void {
      $this->adapter->delete('locked.txt');
    });
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

  private function suppressWarnings(callable $callback): void
  {
    set_error_handler(static fn (): bool => true);

    try {
      $callback();
    } finally {
      restore_error_handler();
    }
  }
}
