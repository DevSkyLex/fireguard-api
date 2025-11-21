<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Exception;

use PHPUnit\Framework\TestCase;
use Shared\Infrastructure\Exception\UuidGenerationException;
use Exception;

/**
 * Class UuidGenerationExceptionTest
 *
 * Unit tests for the UuidGenerationException.
 *
 * @category Unit Test
 * @package Tests\Unit\Shared\Infrastructure\Exception
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 * @covers \Shared\Infrastructure\Exception\UuidGenerationException
 */
final class UuidGenerationExceptionTest extends TestCase
{
  /**
   * Test the dueToRandomFailure factory method.
   */
  public function testDueToRandomFailure(): void
  {
    $previous = new Exception('Random failure');
    $exception = UuidGenerationException::dueToRandomFailure($previous);

    $this->assertSame('Unable to generate a UUID: Random failure', $exception->getMessage());
    $this->assertSame($previous, $exception->getPrevious());
  }
}

