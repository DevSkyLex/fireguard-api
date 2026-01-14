<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Domain\ValueObject\Client;

use OAuth\Domain\ValueObject\Client\RedirectUri;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;

/**
 * Class RedirectUriTest.
 *
 * Unit tests for the RedirectUri Value Object.
 *
 * @category Unit Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @covers \OAuth\Domain\ValueObject\Client\RedirectUri
 */
#[CoversClass(className: RedirectUri::class)]
final class RedirectUriTest extends TestCase
{
  // #region Methods
  /**
   * Method testCanBeCreatedWithValidValue.
   *
   * Tests that a valid RedirectUri can
   * be created.
   *
   * @return void no return value
   */
  #[Test]
  public function testCanBeCreatedWithValidValue(): void
  {
    $value = 'https://example.com/callback';
    $uri = new RedirectUri(value: $value);

    $this->assertEquals(expected: $value, actual: $uri->value);
    $this->assertEquals(expected: $value, actual: (string) $uri);
  }

  /**
   * Method testCannotBeCreatedWithInvalidValue.
   *
   * Tests that creating a RedirectUri with an
   * invalid URL throws an exception.
   *
   * @return void no return value
   */
  #[Test]
  public function testCannotBeCreatedWithInvalidValue(): void
  {
    $this->expectException(exception: InvalidValueException::class);
    new RedirectUri(value: 'invalid-url');
  }

  /**
   * Method testEquality.
   *
   * Tests equality comparison between
   * RedirectUri objects.
   *
   * @return void no return value
   */
  #[Test]
  public function testEquality(): void
  {
    $u1 = new RedirectUri(value: 'https://example.com');
    $u2 = new RedirectUri(value: 'https://example.com');
    $u3 = new RedirectUri(value: 'https://other.com');

    $this->assertTrue(condition: $u1->equals($u2));
    $this->assertFalse(condition: $u1->equals($u3));
  }
  // #endregion
}
