<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Domain\Exception;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\EntityNotFoundException;

/**
 * Test EntityNotFoundExceptionTest.
 *
 * @category Unit Test
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @covers \Shared\Domain\Exception\EntityNotFoundException
 */
#[CoversClass(className: EntityNotFoundException::class)]
final class EntityNotFoundExceptionTest extends TestCase
{
  /**
   * Method testForId.
   *
   * Tests the forId factory method creates an exception
   * with the expected message format.
   *
   * @since 1.0.0
   *
   * @return void no return value
   */
  #[Test]
  public function testForId(): void
  {
    $entityName = 'User';
    $id = '123';
    $exception = EntityNotFoundException::forId($entityName, $id);

    $this->assertSame('User with ID "123" not found.', $exception->getMessage());
  }
}
