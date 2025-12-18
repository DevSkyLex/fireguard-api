<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Domain\ValueObject;

use OAuth\Domain\ValueObject\ClientName;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Shared\Domain\Exception\InvalidValueException;

/**
 * Test ClientNameTest
 * @final
 *
 * Test class for the ClientName value object.
 *
 * @category ValueObject Tests
 * @package Tests\Client\Domain\ValueObject
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: ClientName::class)]
final class ClientNameTest extends TestCase
{
  //#region Methods
  /**
   * Method testValidClientNameIsAccepted
   *
   * Test the constructor with
   * a valid client name
   *
   * @access public
   *
   * @return void No return value
   */
  #[Test]
  public function testValidClientNameIsAccepted(): void
  {
    $clientName = new ClientName(value: 'My OAuth Client');

    self::assertSame(
      expected: 'My OAuth Client',
      actual: $clientName->value
    );
  }

  /**
   * Method testTooShortClientNameThrowsException
   *
   * Test the constructor with
   * a too short client name
   *
   * @access public
   *
   * @return void No return value
   */
  #[Test]
  public function testTooShortClientNameThrowsException(): void
  {
    $this->expectException(exception: InvalidValueException::class);

    new ClientName(value: 'AB');
  }

  /**
   * Method testTooLongClientNameThrowsException
   *
   * Test the constructor with
   * a too long client name
   *
   * @access public
   *
   * @return void No return value
   */
  #[Test]
  public function testTooLongClientNameThrowsException(): void
  {
    $this->expectException(exception: InvalidValueException::class);

    new ClientName(value: str_repeat(string: 'A', times: 101));
  }

  /**
   * Method testEqualsReturnsTrueForSameValue
   *
   * Test the equals method with
   * the same value
   *
   * @access public
   *
   * @return void No return value
   */
  #[Test]
  public function testEqualsReturnsTrueForSameValue(): void
  {
    $clientName1 = new ClientName(value: 'My OAuth Client');
    $clientName2 = new ClientName(value: 'My OAuth Client');

    self::assertTrue(condition: $clientName1->equals(other: $clientName2));
  }

  /**
   * Method testEqualsReturnsFalseForDifferentValue
   *
   * Test the equals method with
   * different values
   *
   * @access public
   *
   * @return void No return value
   */
  #[Test]
  public function testEqualsReturnsFalseForDifferentValue(): void
  {
    $clientName1 = new ClientName(value: 'My OAuth Client');
    $clientName2 = new ClientName(value: 'Another Client');

    self::assertFalse(condition: $clientName1->equals(other: $clientName2));
  }

  /**
   * Method testToStringReturnsValue
   *
   * Test the __toString method
   *
   * @access public
   *
   * @return void No return value
   */
  #[Test]
  public function testToStringReturnsValue(): void
  {
    $clientName = new ClientName(value: 'My OAuth Client');

    self::assertSame(
      expected: 'My OAuth Client',
      actual: (string) $clientName
    );
  }
  //#endregion
}

