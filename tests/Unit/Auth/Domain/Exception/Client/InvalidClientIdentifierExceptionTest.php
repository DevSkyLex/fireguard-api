<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Domain\Exception\Client;

use Auth\Domain\Exception\Client\InvalidClientIdentifierException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test InvalidClientIdentifierExceptionTest.
 *
 * @category Unit Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: InvalidClientIdentifierException::class)]
final class InvalidClientIdentifierExceptionTest extends TestCase
{
  // #region Methods
  /**
   * Method testEmptyCreatesMessage.
   *
   * Tests that empty factory sets expected message.
   */
  #[Test]
  public function testEmptyCreatesMessage(): void
  {
    $exception = InvalidClientIdentifierException::empty();

    $this->assertSame('Client identifier cannot be empty.', $exception->getMessage());
  }

  /**
   * Method testInvalidPatternCreatesMessage.
   *
   * Tests that invalidPattern factory sets expected message.
   */
  #[Test]
  public function testInvalidPatternCreatesMessage(): void
  {
    $exception = InvalidClientIdentifierException::invalidPattern(value: 'bad');

    $this->assertStringContainsString('Invalid client identifier', $exception->getMessage());
  }
  // #endregion
}
