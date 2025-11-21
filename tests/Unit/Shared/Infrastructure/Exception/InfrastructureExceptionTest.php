<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Exception;

use PHPUnit\Framework\TestCase;
use Shared\Infrastructure\Exception\InfrastructureException;

/**
 * Class InfrastructureExceptionTest
 *
 * Unit tests for the InfrastructureException.
 *
 * @category Unit Test
 * @package Tests\Unit\Shared\Infrastructure\Exception
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 * @covers \Shared\Infrastructure\Exception\InfrastructureException
 */
final class InfrastructureExceptionTest extends TestCase
{
  /**
   * Test that metadata returns an empty array by default.
   */
  public function testMetadataReturnsEmptyArrayByDefault(): void
  {
    $exception = new class extends InfrastructureException {};
    $this->assertSame([], $exception->metadata());
  }
}

