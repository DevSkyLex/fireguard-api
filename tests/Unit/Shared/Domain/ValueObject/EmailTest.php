<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Domain\ValueObject;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Shared\Domain\Exception\InvalidValueException;
use Shared\Domain\ValueObject\Email;

/**
 * Class EmailTest
 *
 * Unit tests for the Email Value Object.
 *
 * @category Unit Test
 * @package Tests\Unit\Shared\Domain\ValueObject
 * 
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 * 
 * @covers \Shared\Domain\ValueObject\Email
 */
#[CoversClass(className: Email::class)]
final class EmailTest extends TestCase
{
  //#region Methods
  /**
   * Method testCanBeCreatedWithValidValue
   *
   * Tests that a valid Email can 
   * be created.
   * 
   * @access public
   * 
   * @return void No return value.
   */
  #[Test]
  public function testCanBeCreatedWithValidValue(): void
  {
    $value = 'test@example.com';
    $email = new Email(value: $value);

    $this->assertEquals(expected: $value, actual: $email->value);
    $this->assertEquals(expected: $value, actual: (string) $email);
  }

  /**
   * Method testCannotBeCreatedWithInvalidValue
   *
   * Tests that creating an Email with an 
   * invalid format throws an exception.
   * 
   * @access public
   * 
   * @return void No return value.
   */
  #[Test]
  public function testCannotBeCreatedWithInvalidValue(): void
  {
    $this->expectException(exception: InvalidValueException::class);
    new Email(value: 'invalid-email');
  }

  /**
   * Method testEquality
   *
   * Tests equality comparison between 
   * Email objects.
   * 
   * @access public
   * 
   * @return void No return value.
   */
  #[Test]
  public function testEquality(): void
  {
    $e1 = new Email(value: 'test@example.com');
    $e2 = new Email(value: 'test@example.com');
    $e3 = new Email(value: 'other@example.com');

    $this->assertTrue(condition: $e1->equals($e2));
    $this->assertFalse(condition: $e1->equals($e3));
  }
  //#endregion
}

