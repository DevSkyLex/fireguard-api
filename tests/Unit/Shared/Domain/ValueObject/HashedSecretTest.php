<?php

declare(strict_types=1);

namespace Tests\Shared\Domain\ValueObject;

use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;
use Shared\Domain\ValueObject\HashedSecret;

/**
 * Test HashedSecretTest
 * @final
 *
 * Test class for HashedSecret.
 *
 * @category ValueObject Tests
 * @package Tests\Shared\Domain\ValueObject
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class HashedSecretTest extends TestCase
{
  //#region Methods
  /**
   * Method testValidHashedSecretIsAccepted
   * @method testValidHashedSecretIsAccepted(): void
   *
   * Test the constructor with
   * valid hashed secret.
   *
   * @access public
   *
   * @return void No return value
   */
  public function testValidHashedSecretIsAccepted(): void
  {
    $hashed = new HashedSecret(value: '$argon2id$hash-value');

    self::assertSame(
      expected: '$argon2id$hash-value',
      actual: (string) $hashed
    );
  }

  /**
   * Method testInvalidHashedSecretThrows
   * @method testInvalidHashedSecretThrows(): void
   *
   * Test the constructor with
   * invalid hashed secret.
   *
   * @access public
   *
   * @return void No return value
   */
  public function testInvalidHashedSecretThrows(): void
  {
    $this->expectException(exception: InvalidValueException::class);

    new HashedSecret(value: 'plain-value');
  }
  //#endregion
}
