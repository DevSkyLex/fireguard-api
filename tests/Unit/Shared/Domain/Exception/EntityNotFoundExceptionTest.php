<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Domain\Exception;

use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\EntityNotFoundException;

/**
 * Test EntityNotFoundExceptionTest
 * @final
 *
 * Unit tests for the EntityNotFoundException.
 *
 * @category Unit Test
 * @package Tests\Unit\Shared\Domain\Exception
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 * 
 * @covers \Shared\Domain\Exception\EntityNotFoundException
 */
final class EntityNotFoundExceptionTest extends TestCase
{
  /**
   * Method testForId
   *
   * Tests the forId factory method creates an exception
   * with the expected message format.
   *
   * @access public
   * @since 1.0.0
   *
   * @return void No return value.
   */
  public function testForId(): void
  {
    $entityName = 'User';
    $id = '123';
    $exception = EntityNotFoundException::forId($entityName, $id);

    $this->assertSame('User with ID "123" not found.', $exception->getMessage());
  }
}

