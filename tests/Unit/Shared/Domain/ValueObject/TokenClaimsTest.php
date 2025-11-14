<?php

declare(strict_types=1);

namespace Tests\Shared\Domain\ValueObject;

use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;
use Shared\Domain\ValueObject\TokenClaims;

/**
 * Test TokenClaimsTest
 * @final
 *
 * Test class for TokenClaims.
 *
 * @category ValueObject Tests
 * @package Tests\Shared\Domain\ValueObject
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class TokenClaimsTest extends TestCase
{
  //#region Methods
  /**
   * Method testConstructWithValidClaims
   *
   * Test the constructor with
   * valid claims.
   *
   * @access public
   * @since 1.0.0
   *
   * @return void No return value
   */
  public function testConstructWithValidClaims(): void
  {
    $claims = new TokenClaims(claims: [
      'sub' => 'user-1',
      'aud' => 'api'
    ]);

    self::assertSame(
      expected: ['sub' => 'user-1', 'aud' => 'api'],
      actual: $claims->toArray()
    );

    self::assertTrue(condition: $claims->has('sub'));

    self::assertSame(
      expected: 'user-1',
      actual: $claims->get('sub')
    );
  }

  /**
   * Method testConstructWithEmptyClaimsThrows
   *
   * Test the constructor with
   * empty claims.
   *
   * @access public
   * @since 1.0.0
   *
   * @return void No return value
   */
  public function testConstructWithEmptyClaimsThrows(): void
  {
    $this->expectException(exception: InvalidValueException::class);

    new TokenClaims(claims: []);
  }

  /**
   * Method testConstructWithInvalidKeyThrows
   *
   * Test the constructor with
   * invalid key.
   *
   * @access public
   *
   * @return void No return value
   */
  public function testConstructWithInvalidKeyThrows(): void
  {
    $this->expectException(exception: InvalidValueException::class);

    new TokenClaims(claims: ['' => 'value']);
  }

  /**
   * Method testJsonSerialize
   *
   * Test the jsonSerialize method.
   *
   * @access public
   *
   * @return void No return value
   */
  public function testJsonSerialize(): void
  {
    $claims = new TokenClaims(claims: [
      'sub' => 'user-1',
      'aud' => 'api'
    ]);

    self::assertSame(
      expected: ['sub' => 'user-1', 'aud' => 'api'],
      actual: $claims->jsonSerialize()
    );
  }
  //#endregion
}
