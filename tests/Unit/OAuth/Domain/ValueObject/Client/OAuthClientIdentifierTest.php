<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Domain\ValueObject\Client;

use OAuth\Domain\Exception\Client\InvalidOAuthClientIdentifierException;
use OAuth\Domain\ValueObject\Client\OAuthClientIdentifier;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Class OAuthClientIdentifierTest.
 *
 * Unit tests for the OAuthClientIdentifier Value Object.
 *
 * @category Unit Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @covers \OAuth\Domain\ValueObject\Client\OAuthClientIdentifier
 */
#[CoversClass(className: OAuthClientIdentifier::class)]
final class OAuthClientIdentifierTest extends TestCase
{
  // #region Methods
  /**
   * Method testCanBeCreatedWithValidValue.
   *
   * Test that a valid OAuthClientIdentifier can be created.
   *
   * @return void no return value
   */
  #[Test]
  public function testCanBeCreatedWithValidValue(): void
  {
    $value = 'valid-client-id';
    $clientId = new OAuthClientIdentifier(value: $value);

    $this->assertEquals(expected: $value, actual: $clientId->value);
    $this->assertEquals(expected: $value, actual: (string) $clientId);
  }

  /**
   * Method testCannotBeCreatedWithEmptyValue.
   *
   * Test that creating an OAuthClientIdentifier with an empty
   * value throws an exception.
   *
   * @return void no return value
   */
  #[Test]
  public function testCannotBeCreatedWithEmptyValue(): void
  {
    $this->expectException(exception: InvalidOAuthClientIdentifierException::class);
    $this->expectExceptionMessage(message: 'OAuth client identifier cannot be empty.');
    new OAuthClientIdentifier(value: '');
  }

  /**
   * Method testCannotBeCreatedWithInvalidCharacters.
   *
   * Test that creating an OAuthClientIdentifier with invalid
   * characters throws an exception.
   *
   * @return void no return value
   */
  #[Test]
  public function testCannotBeCreatedWithInvalidCharacters(): void
  {
    $this->expectException(exception: InvalidOAuthClientIdentifierException::class);
    $this->expectExceptionMessage(message: 'Invalid OAuth client identifier');
    new OAuthClientIdentifier(value: 'invalid client id with spaces');
  }

  /**
   * Method testCannotBeCreatedWithTooShortValue.
   *
   * Test that creating an OAuthClientIdentifier with a value
   * that is too short throws an exception.
   *
   * @return void no return value
   */
  #[Test]
  public function testCannotBeCreatedWithTooShortValue(): void
  {
    $this->expectException(exception: InvalidOAuthClientIdentifierException::class);
    $this->expectExceptionMessage(message: 'Invalid OAuth client identifier');
    new OAuthClientIdentifier(value: 'a');
  }

  /**
   * Method testCannotStartWithSpecialCharacter.
   *
   * Test that creating an OAuthClientIdentifier that starts
   * with a special character throws an exception.
   *
   * @return void no return value
   */
  #[Test]
  public function testCannotStartWithSpecialCharacter(): void
  {
    $this->expectException(exception: InvalidOAuthClientIdentifierException::class);
    $this->expectExceptionMessage(message: 'Invalid OAuth client identifier');
    new OAuthClientIdentifier(value: '-invalid-start');
  }

  /**
   * Method testEquality.
   *
   * Test equality comparison between
   * OAuthClientIdentifier objects.
   *
   * @return void no return value
   */
  #[Test]
  public function testEquality(): void
  {
    $id1 = new OAuthClientIdentifier(value: 'client-1');
    $id2 = new OAuthClientIdentifier(value: 'client-1');
    $id3 = new OAuthClientIdentifier(value: 'client-2');

    $this->assertTrue(condition: $id1->equals($id2));
    $this->assertFalse(condition: $id1->equals($id3));
  }

  // #endregion
}
