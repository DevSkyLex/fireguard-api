<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Domain\ValueObject;

use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;
use Shared\Domain\ValueObject\ClientId;

/**
 * Class ClientIdTest
 *
 * Unit tests for the ClientId Value Object.
 *
 * @category Unit Test
 * @package Tests\Unit\Shared\Domain\ValueObject
 * 
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 * 
 * @covers \Shared\Domain\ValueObject\ClientId
 */
final class ClientIdTest extends TestCase
{
  //#region Methods
  /**
   * Method testCanBeCreatedWithValidValue
   *
   * Test that a valid ClientId can be created.
   * 
   * @access public
   * 
   * @return void No return value.
   */
  public function testCanBeCreatedWithValidValue(): void
  {
    $value = 'valid-client-id';
    $clientId = new ClientId(value: $value);

    $this->assertEquals(expected: $value, actual: $clientId->value);
    $this->assertEquals(expected: $value, actual: (string) $clientId);
  }

  /**
   * Method testCannotBeCreatedWithEmptyValue
   *
   * Test that creating a ClientId with an empty 
   * value throws an exception.
   * 
   * @access public
   * 
   * @return void No return value.
   */
  public function testCannotBeCreatedWithEmptyValue(): void
  {
    $this->expectException(exception: InvalidValueException::class);
    new ClientId(value: '');
  }

  /**
   * Method testCannotBeCreatedWithInvalidCharacters
   *
   * Test that creating a ClientId with invalid 
   * characters throws an exception.
   * 
   * @access public
   * 
   * @return void No return value.
   */
  public function testCannotBeCreatedWithInvalidCharacters(): void
  {
    $this->expectException(exception: InvalidValueException::class);
    new ClientId(value: 'invalid client id with spaces');
  }

  /**
   * Method testCannotBeCreatedWithTooShortValue
   *
   * Test that creating a ClientId with a value 
   * that is too short throws an exception.
   * 
   * @access public
   * 
   * @return void No return value.
   */
  public function testCannotBeCreatedWithTooShortValue(): void
  {
    $this->expectException(exception: InvalidValueException::class);
    new ClientId(value: 'a');
  }

  /**
   * Method testEquality
   *
   * Test equality comparison between 
   * ClientId objects.
   * 
   * @access public
   * 
   * @return void No return value.
   */
  public function testEquality(): void
  {
    $id1 = new ClientId(value: 'client-1');
    $id2 = new ClientId(value: 'client-1');
    $id3 = new ClientId(value: 'client-2');

    $this->assertTrue(condition: $id1->equals($id2));
    $this->assertFalse(condition: $id1->equals($id3));
  }

  //#endregion
}
