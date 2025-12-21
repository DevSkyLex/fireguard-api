<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Exception;

use Exception;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shared\Infrastructure\Exception\UuidGenerationException;

/**
 * Class UuidGenerationExceptionTest.
 *
 * Unit tests for the UuidGenerationException.
 *
 * @category Unit Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @covers \Shared\Infrastructure\Exception\UuidGenerationException
 */
#[CoversClass(className: UuidGenerationException::class)]
final class UuidGenerationExceptionTest extends TestCase
{
  /**
   * Test the dueToRandomFailure factory method.
   */
  #[Test]
  public function testDueToRandomFailure(): void
  {
    $previous = new Exception('Random failure');
    $exception = UuidGenerationException::dueToRandomFailure($previous);

    $this->assertSame('Unable to generate a UUID: Random failure', $exception->getMessage());
    $this->assertSame($previous, $exception->getPrevious());
  }
}
