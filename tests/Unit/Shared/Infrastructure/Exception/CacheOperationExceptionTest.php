<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Exception;

use Exception;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shared\Infrastructure\Exception\CacheOperationException;

/**
 * Class CacheOperationExceptionTest.
 *
 * Unit tests for the CacheOperationException.
 *
 * @category Unit Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @covers \Shared\Infrastructure\Exception\CacheOperationException
 */
#[CoversClass(className: CacheOperationException::class)]
final class CacheOperationExceptionTest extends TestCase
{
  /**
   * Test the readFailed factory method.
   */
  #[Test]
  public function testReadFailed(): void
  {
    $previous = new Exception('Previous error');
    $exception = CacheOperationException::readFailed('key', $previous);

    $this->assertSame('Failed to read cache entry "key".', $exception->getMessage());
    $this->assertSame($previous, $exception->getPrevious());
  }

  /**
   * Test the writeFailed factory method.
   */
  #[Test]
  public function testWriteFailed(): void
  {
    $previous = new Exception('Previous error');
    $exception = CacheOperationException::writeFailed('key', $previous);

    $this->assertSame('Failed to write cache entry "key".', $exception->getMessage());
    $this->assertSame($previous, $exception->getPrevious());
  }

  /**
   * Test the deleteFailed factory method.
   */
  #[Test]
  public function testDeleteFailed(): void
  {
    $previous = new Exception('Previous error');
    $exception = CacheOperationException::deleteFailed('key', $previous);

    $this->assertSame('Failed to delete cache entry "key".', $exception->getMessage());
    $this->assertSame($previous, $exception->getPrevious());
  }

  /**
   * Test the clearFailed factory method.
   */
  #[Test]
  public function testClearFailed(): void
  {
    $previous = new Exception('Previous error');
    $exception = CacheOperationException::clearFailed($previous);

    $this->assertSame('Failed to clear cache storage.', $exception->getMessage());
    $this->assertSame($previous, $exception->getPrevious());
  }
}
