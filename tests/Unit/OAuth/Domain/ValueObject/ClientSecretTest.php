<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Domain\ValueObject;

use OAuth\Domain\ValueObject\ClientSecret;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Shared\Domain\Exception\InvalidValueException;

/**
 * Test ClientSecretTest
 * @final
 *
 * Test class for the ClientSecret value object.
 *
 * @category ValueObject Tests
 * @package Tests\Client\Domain\ValueObject
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: ClientSecret::class)]
final class ClientSecretTest extends TestCase
{
  //#region Methods
  /**
   * Method testValidHashedSecretIsAccepted
   *
   * Test the constructor with
   * a valid hashed secret
   *
   * @access public
   *
   * @return void No return value
   */
  #[Test]
  public function testValidHashedSecretIsAccepted(): void
  {
    $hashedValue = '$2y$10$abcdefghijklmnopqrstuv';
    $clientSecret = new ClientSecret(value: $hashedValue);

    self::assertSame(
      expected: $hashedValue,
      actual: $clientSecret->value
    );
  }

  /**
   * Method testGenerateRandomPlainReturnsValidSecret
   *
   * Test the generateRandomPlain method
   *
   * @access public
   *
   * @return void No return value
   */
  #[Test]
  public function testGenerateRandomPlainReturnsValidSecret(): void
  {
    $plainSecret = ClientSecret::generateRandomPlain();

    self::assertGreaterThanOrEqual(
      32,
      strlen(string: $plainSecret)
    );
  }

  /**
   * Method testGenerateRandomPlainReturnsDifferentValues
   *
   * Test that generateRandomPlain
   * returns different values
   *
   * @access public
   *
   * @return void No return value
   */
  #[Test]
  public function testGenerateRandomPlainReturnsDifferentValues(): void
  {
    $plainSecret1 = ClientSecret::generateRandomPlain();
    $plainSecret2 = ClientSecret::generateRandomPlain();

    self::assertNotEquals(
      expected: $plainSecret1,
      actual: $plainSecret2
    );
  }

  /**
   * Method testGenerateRandomPlainWithCustomLength
   *
   * Test generateRandomPlain with
   * custom length
   *
   * @access public
   *
   * @return void No return value
   */
  #[Test]
  public function testGenerateRandomPlainWithCustomLength(): void
  {
    $plainSecret = ClientSecret::generateRandomPlain(length: 64);

    self::assertGreaterThanOrEqual(
      64,
      strlen(string: $plainSecret)
    );
  }

  /**
   * Method testInvalidSecretThrowsException
   *
   * Test the constructor with
   * an invalid (non-hashed) secret
   *
   * @access public
   *
   * @return void No return value
   */
  #[Test]
  public function testInvalidSecretThrowsException(): void
  {
    $this->expectException(exception: InvalidValueException::class);

    new ClientSecret(value: 'plain-text-secret');
  }

  /**
   * Method testEmptySecretThrowsException
   *
   * Test the constructor with
   * an empty secret
   *
   * @access public
   *
   * @return void No return value
   */
  #[Test]
  public function testEmptySecretThrowsException(): void
  {
    $this->expectException(exception: InvalidValueException::class);

    new ClientSecret(value: '');
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
    $hashedValue = '$2y$10$abcdefghijklmnopqrstuv';
    $clientSecret = new ClientSecret(value: $hashedValue);

    self::assertSame(
      expected: $hashedValue,
      actual: (string) $clientSecret
    );
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
    $hashedValue = '$2y$10$abcdefghijklmnopqrstuv';
    $clientSecret1 = new ClientSecret(value: $hashedValue);
    $clientSecret2 = new ClientSecret(value: $hashedValue);

    self::assertTrue(condition: $clientSecret1->equals(other: $clientSecret2));
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
    $clientSecret1 = new ClientSecret(value: '$2y$10$abcdefghijklmnopqrstuv');
    $clientSecret2 = new ClientSecret(value: '$2y$10$zyxwvutsrqponmlkjihgf');

    self::assertFalse(condition: $clientSecret1->equals(other: $clientSecret2));
  }
  //#endregion
}

