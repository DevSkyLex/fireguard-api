<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Exception;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Shared\Infrastructure\Exception\FileStorageException;
use Exception;

/**
 * Class FileStorageExceptionTest
 *
 * Unit tests for the FileStorageException.
 *
 * @category Unit Test
 * @package Tests\Unit\Shared\Infrastructure\Exception
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 * @covers \Shared\Infrastructure\Exception\FileStorageException
 */
#[CoversClass(className: FileStorageException::class)]
final class FileStorageExceptionTest extends TestCase
{
  /**
   * Test the readFailed factory method.
   */
  #[Test]
  public function testReadFailed(): void
  {
    $previous = new Exception('Previous error');
    $exception = FileStorageException::readFailed('/path/to/file', $previous);

    $this->assertSame('Failed to read file "/path/to/file".', $exception->getMessage());
    $this->assertSame($previous, $exception->getPrevious());
  }

  /**
   * Test the writeFailed factory method.
   */
  #[Test]
  public function testWriteFailed(): void
  {
    $previous = new Exception('Previous error');
    $exception = FileStorageException::writeFailed('/path/to/file', $previous);

    $this->assertSame('Failed to write file "/path/to/file".', $exception->getMessage());
    $this->assertSame($previous, $exception->getPrevious());
  }

  /**
   * Test the deleteFailed factory method.
   */
  #[Test]
  public function testDeleteFailed(): void
  {
    $previous = new Exception('Previous error');
    $exception = FileStorageException::deleteFailed('/path/to/file', $previous);

    $this->assertSame('Failed to delete file "/path/to/file".', $exception->getMessage());
    $this->assertSame($previous, $exception->getPrevious());
  }

  /**
   * Test the directoryCreationFailed factory method.
   */
  #[Test]
  public function testDirectoryCreationFailed(): void
  {
    $previous = new Exception('Previous error');
    $exception = FileStorageException::directoryCreationFailed('/path/to/dir', $previous);

    $this->assertSame('Failed to create directory "/path/to/dir".', $exception->getMessage());
    $this->assertSame($previous, $exception->getPrevious());
  }
}

