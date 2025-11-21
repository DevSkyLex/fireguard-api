<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Domain\ValueObject;

use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;
use Shared\Domain\ValueObject\RedirectUri;

/**
 * Class RedirectUriTest
 *
 * Unit tests for the RedirectUri Value Object.
 *
 * @category Unit Test
 * @package Tests\Unit\Shared\Domain\ValueObject
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 * 
 * @covers \Shared\Domain\ValueObject\RedirectUri
 */
final class RedirectUriTest extends TestCase
{
  //#region Methods
  /**
   * Method testCanBeCreatedWithValidValue
   *
   * Tests that a valid RedirectUri can 
   * be created.
   *
   * @access public
   *
   * @return void No return value.
   */
  public function testCanBeCreatedWithValidValue(): void
  {
    $value = 'https://example.com/callback';
    $uri = new RedirectUri(value: $value);

    $this->assertEquals(expected: $value, actual: $uri->value);
    $this->assertEquals(expected: $value, actual: (string) $uri);
  }

  /**
   * Method testCannotBeCreatedWithInvalidValue
   *
   * Tests that creating a RedirectUri with an 
   * invalid URL throws an exception.
   *
   * @access public
   *
   * @return void No return value.
   */
  public function testCannotBeCreatedWithInvalidValue(): void
  {
    $this->expectException(exception: InvalidValueException::class);
    new RedirectUri(value: 'invalid-url');
  }

  /**
   * Method testEquality
   *
   * Tests equality comparison between 
   * RedirectUri objects.
   *
   * @access public
   *
   * @return void No return value.
   */
  public function testEquality(): void
  {
    $u1 = new RedirectUri(value: 'https://example.com');
    $u2 = new RedirectUri(value: 'https://example.com');
    $u3 = new RedirectUri(value: 'https://other.com');

    $this->assertTrue(condition: $u1->equals($u2));
    $this->assertFalse(condition: $u1->equals($u3));
  }
  //#endregion
}

