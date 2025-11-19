<?php

declare(strict_types=1);

namespace Tests\Shared\Infrastructure\Symfony\Adapter\Outbound;

use PHPUnit\Framework\TestCase;
use Shared\Infrastructure\Symfony\Adapter\Outbound\FileStorageAdapter;
use Shared\Infrastructure\Exception\FileStorageException;

use function file_put_contents;
use function is_dir;
use function is_file;
use function mkdir;
use function restore_error_handler;
use function rmdir;
use function scandir;
use function set_error_handler;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

/**
 * Test FileStorageAdapter
 * @final
 *
 * Test the FileStorageAdapter class.
 *
 * @category Infrastructure Test
 * @package Tests\Shared\Infrastructure\Symfony\Adapter\Outbound
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class FileStorageAdapterTest extends TestCase
{
  //#region Properties
  /**
   * Property basePath
   *
   * The base path for file storage.
   *
   * @access private
   *
   * @var string $basePath
   */
  private string $basePath;
  //#endregion

  //#region Methods
  /**
   * Method setUp
   *
   * Set up the test environment.
   *
   * @access protected
   *
   * @return void No return value
   */
  protected function setUp(): void
  {
    parent::setUp();

    $this->basePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid(prefix: 'storage_', more_entropy: true);

    // Create a temporary directory for storage
    mkdir(
      directory: $this->basePath,
      permissions: 0775,
      recursive: true
    );
  }

  /**
   * Method tearDown
   *
   * Tear down the test environment.
   *
   * @access protected
   *
   * @return void No return value
   */
  protected function tearDown(): void
  {
    $this->removeDirectory($this->basePath);

    parent::tearDown();
  }

  /**
   * Method removeDirectory
   *
   * Remove a directory and its contents recursively.
   *
   * @access private
   *
   * @param string $path The path to the directory to remove.
   *
   * @return void No return value
   */
  private function removeDirectory(string $path): void
  {
    if (!is_dir($path)) return;

    $files = scandir($path);

    foreach ($files ?: [] as $file) {
      if ($file === '.' || $file === '..') continue;

      $absolute = $path . DIRECTORY_SEPARATOR . $file;

      if (is_dir($absolute)) {
        $this->removeDirectory($absolute);
        continue;
      }

      unlink($absolute);
    }

    rmdir($path);
  }

  /**
   * Method ignoreFilesystemWarnings
   *
   * Execute a callback while silencing filesystem warnings.
   *
   * @param callable():void $callback
   */
  private function ignoreFilesystemWarnings(callable $callback): void
  {
    set_error_handler(static fn() => true);

    try {
      $callback();
    }
    finally {
      restore_error_handler();
    }
  }

  /**
   * Method testWriteAndReadFile
   *
   * Test that the write and read methods work as expected.
   *
   * @access public
   *
   * @return void No return value
   */
  public function testWriteAndReadFile(): void
  {
    $adapter = new FileStorageAdapter(basePath: $this->basePath);

    $adapter->write(
      path: 'dir/file.txt',
      contents: 'content'
    );

    self::assertSame(
      expected: 'content',
      actual: $adapter->read(path: 'dir/file.txt')
    );
  }

  /**
   * Method testDeleteRemovesFile
   *
   * Test that the delete method removes a file.
   *
   * @access public
   *
   * @return void No return value
   */
  public function testDeleteRemovesFile(): void
  {
    $adapter = new FileStorageAdapter(basePath: $this->basePath);

    $adapter->write(
      path: 'file.txt',
      contents: 'content'
    );

    self::assertTrue(condition: $adapter->exists(path: 'file.txt'));

    $adapter->delete(path: 'file.txt');

    self::assertFalse(condition: $adapter->exists(path: 'file.txt'));
  }

  /**
   * Method testExistsReturnsFalseForMissingFile
   *
   * Test that the exists method returns
   * false for a missing file.
   *
   * @access public
   *
   * @return void No return value
   */
  public function testExistsReturnsFalseForMissingFile(): void
  {
    $adapter = new FileStorageAdapter(basePath: $this->basePath);

    self::assertFalse(condition: $adapter->exists(path: 'missing.txt'));
  }

  /**
   * Method testReadThrowsWhenFileMissing
   *
   * Test that the read method throws an
   * exception when the file is missing.
   *
   * @access public
   *
   * @return void No return value
   */
  public function testReadThrowsWhenFileMissing(): void
  {
    $adapter = new FileStorageAdapter(basePath: $this->basePath);

    $this->expectException(exception: FileStorageException::class);
    $adapter->read(path: 'missing.txt');
  }

  /**
   * Method testWriteThrowsWhenDirectoryCreationFails
   *
   * Test that the write method throws an
   * exception when the directory creation fails.
   *
   * @access public
   *
   * @return void No return value
   */
  public function testWriteThrowsWhenDirectoryCreationFails(): void
  {
    $adapter = new FileStorageAdapter(basePath: $this->basePath);

    // Create a file where the directory should be, forcing mkdir failure
    $collisionPath = $this->basePath . DIRECTORY_SEPARATOR . 'dir';
    file_put_contents($collisionPath, 'content');

    $this->expectException(exception: FileStorageException::class);

    $this->ignoreFilesystemWarnings(static function () use ($adapter) {
      $adapter->write(
        path: 'dir/file.txt',
        contents: 'content'
      );
    });
  }

  /**
   * Method testWriteThrowsWhenFilePutContentsFails
   *
   * Test that the write method throws an
   * exception when file_put_contents fails.
   *
   * @access public
   *
   * @return void No return value
   */
  public function testWriteThrowsWhenFilePutContentsFails(): void
  {
    $adapter = new FileStorageAdapter(basePath: $this->basePath);

    $fullPath = $this->basePath . DIRECTORY_SEPARATOR . 'dir' . DIRECTORY_SEPARATOR . 'file.txt';
    mkdir(
      directory: $fullPath,
      permissions: 0775,
      recursive: true
    );

    $this->expectException(exception: FileStorageException::class);

    $this->ignoreFilesystemWarnings(callback: static function () use ($adapter): void {
      $adapter->write(
        path: 'dir/file.txt',
        contents: 'content'
      );
    });
  }

  /**
   * Method testDeleteThrowsWhenUnlinkFails
   *
   * Test that the delete method throws an
   * exception when unlink fails.
   *
   * @access public
   *
   * @return void No return value
   */
  public function testDeleteThrowsWhenUnlinkFails(): void
  {
    $adapter = new FileStorageAdapter(basePath: $this->basePath);

    $fullPath = $this->basePath . DIRECTORY_SEPARATOR . 'dir' . DIRECTORY_SEPARATOR . 'file.txt';
    mkdir(
      directory: $fullPath,
      permissions: 0775,
      recursive: true
    );

    $this->expectException(exception: FileStorageException::class);

    $this->ignoreFilesystemWarnings(static function () use ($adapter) {
      $adapter->delete(path: 'dir/file.txt');
    });
  }
  //#endregion
}
