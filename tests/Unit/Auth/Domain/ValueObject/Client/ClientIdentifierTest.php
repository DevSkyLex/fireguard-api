<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Domain\ValueObject\Client;

use Auth\Domain\Exception\Client\InvalidClientIdentifierException;
use Auth\Domain\ValueObject\Client\ClientIdentifier;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test ClientIdentifierTest.
 *
 * @category Unit Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: ClientIdentifier::class)]
final class ClientIdentifierTest extends TestCase
{
  // #region Methods
  /**
   * Method testValidIdentifierIsAccepted.
   *
   * Tests that a valid identifier is accepted.
   */
  #[Test]
  public function testValidIdentifierIsAccepted(): void
  {
    $identifier = new ClientIdentifier(value: 'client_123');

    $this->assertSame('client_123', $identifier->value);
    $this->assertSame('client_123', (string) $identifier);
  }

  /**
   * Method testEmptyIdentifierThrows.
   *
   * Tests that empty identifier throws an exception.
   */
  #[Test]
  public function testEmptyIdentifierThrows(): void
  {
    $this->expectException(InvalidClientIdentifierException::class);

    new ClientIdentifier(value: '');
  }

  /**
   * Method testInvalidPatternThrows.
   *
   * Tests that invalid identifier throws an exception.
   */
  #[Test]
  public function testInvalidPatternThrows(): void
  {
    $this->expectException(InvalidClientIdentifierException::class);

    new ClientIdentifier(value: '!!invalid');
  }

  /**
   * Method testEqualsComparesValue.
   *
   * Tests that equals compares identifier values.
   */
  #[Test]
  public function testEqualsComparesValue(): void
  {
    $first = new ClientIdentifier(value: 'client_1');
    $second = new ClientIdentifier(value: 'client_1');
    $third = new ClientIdentifier(value: 'client_2');

    $this->assertTrue($first->equals($second));
    $this->assertFalse($first->equals($third));
  }
  // #endregion
}
