<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Domain\ValueObject\Token;

use Auth\Domain\ValueObject\Token\TokenIdentifier;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test TokenIdentifierTest.
 *
 * @category Unit Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: TokenIdentifier::class)]
final class TokenIdentifierTest extends TestCase
{
  // #region Methods
  /**
   * Method testConstructorRejectsEmptyValue.
   *
   * Tests that empty identifiers throw an exception.
   */
  #[Test]
  public function testConstructorRejectsEmptyValue(): void
  {
    $this->expectException(InvalidArgumentException::class);

    new TokenIdentifier(value: '');
  }

  /**
   * Method testGenerateCreatesIdentifier.
   *
   * Tests that generate creates a non-empty identifier.
   */
  #[Test]
  public function testGenerateCreatesIdentifier(): void
  {
    $identifier = TokenIdentifier::generate();

    $this->assertNotSame('', $identifier->value);
    $this->assertSame($identifier->value, (string) $identifier);
  }

  /**
   * Method testEqualsComparesValue.
   *
   * Tests that equals compares identifier values.
   */
  #[Test]
  public function testEqualsComparesValue(): void
  {
    $first = new TokenIdentifier(value: 'token-1');
    $second = new TokenIdentifier(value: 'token-1');
    $third = new TokenIdentifier(value: 'token-2');

    $this->assertTrue($first->equals($second));
    $this->assertFalse($first->equals($third));
  }
  // #endregion
}
