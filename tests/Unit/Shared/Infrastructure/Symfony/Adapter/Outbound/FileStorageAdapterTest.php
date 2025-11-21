<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Symfony\Adapter\Outbound;

use PHPUnit\Framework\TestCase;
use Shared\Infrastructure\Exception\FileStorageException;
use Shared\Infrastructure\Symfony\Adapter\Outbound\FileStorageAdapter;

/**
 * Test FileStorageAdapterTest
 *
 * Unit tests for the FileStorageAdapter.
 *
 * @category Unit Test
 * @package Tests\Unit\Shared\Infrastructure\Symfony\Adapter\Outbound
 * 
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 * 
 * @covers \Shared\Infrastructure\Symfony\Adapter\Outbound\FileStorageAdapter
 */
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
   * Recursively remove a directory and its contents.
   *
   * @param string $dir The directory to remove.
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

  /**
   * Test that a file can be written and read.
   */
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
  public function testReadThrowsExceptionIfFileNotFound(): void
  {
    $this->expectException(FileStorageException::class);
    $this->adapter->read('non_existent.txt');
  }

  /**
   * Test that a file can be deleted.
   */
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
  public function testDeleteNonExistentFileDoesNotThrow(): void
  {
    $this->expectNotToPerformAssertions();
    $this->adapter->delete('non_existent.txt');
  }
}

