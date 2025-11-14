<?php

declare(strict_types=1);

namespace Tests\Shared\Domain\ValueObject;

use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;
use Shared\Domain\ValueObject\RedirectUri;

/**
 * Test RedirectUriTest
 * @extends TestCase
 * @final
 *
 * Test class for RedirectUri.
 *
 * @category ValueObject Tests
 * @package Tests\Shared\Domain\ValueObject
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class RedirectUriTest extends TestCase
{
  //#region Constants
  /**
   * Constant VALID_URI
   *
   * Valid URI
   *
   * @access private
   *
   * @var string VALID_URI
   */
  private const string VALID_URI = 'https://example.com/callback';

  /**
   * Constant INVALID_SCHEME_URI
   *
   * Invalid URI with HTTP scheme
   *
   * @access private
   *
   * @var string INVALID_SCHEME_URI
   */
  private const string INVALID_SCHEME_URI = 'http://example.com/callback';

  /**
   * Constant INVALID_URI
   *
   * Invalid URI
   *
   * @access private
   *
   * @var string INVALID_URI
   */
  private const string INVALID_URI = 'not-a-url';
  //#endregion

  //#region Methods
  /**
   * Method testValidRedirectUriIsAccepted
   * @method testValidRedirectUriIsAccepted(): void
   *
   * Test the constructor with
   * a valid URI
   *
   * @access public
   *
   * @return void No return value
   */
  public function testValidRedirectUriIsAccepted(): void
  {
    $uri = new RedirectUri(value: self::VALID_URI);

    self::assertSame(
      expected: self::VALID_URI,
      actual: (string) $uri
    );
  }

  /**
   * Method testRejectsNonHttpsScheme
   * @method testRejectsNonHttpsScheme(): void
   *
   * Test the constructor with
   * a non-HTTPS URI
   *
   * @access public
   *
   * @return void No return value
   */
  public function testRejectsNonHttpsScheme(): void
  {
    $this->expectException(exception: InvalidValueException::class);

    new RedirectUri(value: self::INVALID_SCHEME_URI);
  }

  /**
   * Method testRejectsInvalidUri
   * @method testRejectsInvalidUri(): void
   *
   * Test the constructor with
   * an invalid URI
   *
   * @access public
   *
   * @return void No return value
   */
  public function testRejectsInvalidUri(): void
  {
    $this->expectException(exception: InvalidValueException::class);

    new RedirectUri(value: self::INVALID_URI);
  }
  //#endregion
}
