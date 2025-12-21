<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shared\Infrastructure\Exception\NoHandlerResultException;
use stdClass;

/**
 * Class NoHandlerResultExceptionTest.
 *
 * Unit tests for the NoHandlerResultException.
 *
 * @category Unit Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @covers \Shared\Infrastructure\Exception\NoHandlerResultException
 */
#[CoversClass(className: NoHandlerResultException::class)]
final class NoHandlerResultExceptionTest extends TestCase
{
  /**
   * Test the forMessage factory method.
   */
  #[Test]
  public function testForMessage(): void
  {
    $message = new stdClass();
    $exception = NoHandlerResultException::forMessage($message);

    $this->assertSame('No handler result returned for message "stdClass".', $exception->getMessage());
  }
}
