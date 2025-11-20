<?php

declare(strict_types=1);

namespace Shared\Domain\ValueObject;

use Shared\Domain\Exception\InvalidValueException;
use Stringable;

/**
 * ValueObject Scope
 * @final
 *
 * Represents an OAuth 2.0 scope.
 * Scopes define the level of access granted to a client.
 *
 * @category ValueObject
 * @package Shared\Domain\ValueObject
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class Scope implements Stringable
{
  //#region Constants
  /**
   * Constant PATTERN
   *
   * Pattern for valid OAuth scopes.
   * Scopes must be alphanumeric with optional dots, hyphens, or underscores.
   *
   * @access private
   * @since 1.0.0
   *
   * @var string PATTERN
   */
  private const string PATTERN = '/^[a-z][a-z0-9._-]{0,63}$/';

  /**
   * Constant OPENID
   *
   * OpenID Connect scope.
   *
   * @access public
   * @since 1.0.0
   *
   * @var string OPENID
   */
  public const string OPENID = 'openid';

  /**
   * Constant PROFILE
   *
   * OpenID Connect scope.
   *
   * @access public
   * @since 1.0.0
   *
   * @var string PROFILE
   */
  public const string PROFILE = 'profile';

  /**
   * Constant EMAIL
   *
   * OpenID Connect scope.
   *
   * @access public
   * @since 1.0.0
   *
   * @var string EMAIL
   */
  public const string EMAIL = 'email';

  /**
   * Constant PHONE
   *
   * OpenID Connect scope.
   *
   * @access public
   * @since 1.0.0
   *
   * @var string PHONE
   */
  public const string PHONE = 'phone';

  /**
   * Constant ADDRESS
   *
   * OpenID Connect scope.
   *
   * @access public
   * @since 1.0.0
   *
   * @var string ADDRESS
   */
  public const string ADDRESS = 'address';
  
  /**
   * Constant READ
   *
   * Custom application scope.
   *
   * @access public
   * @since 1.0.0
   *
   * @var string READ
   */
  public const string READ = 'read';

  /**
   * Constant WRITE
   *
   * Custom application scope.
   *
   * @access public
   * @since 1.0.0
   *
   * @var string WRITE
   */
  public const string WRITE = 'write';

  /**
   * Constant ADMIN
   *
   * Custom application scope.
   *
   * @access public
   * @since 1.0.0
   *
   * @var string ADMIN
   */
  public const string ADMIN = 'admin';
  //#endregion

  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the Scope class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $value The scope value.
   *
   * @throws InvalidValueException If the scope is invalid.
   */
  public function __construct(public string $value)
  {
    if (!preg_match(pattern: self::PATTERN, subject: $value)) {
      throw InvalidValueException::because(
        message: sprintf(
          'Invalid OAuth scope "%s". Must be lowercase alphanumeric with optional dots, hyphens, or underscores.',
          $value
        )
      );
    }
  }
  //#endregion

  //#region Methods
  /**
   * Method equals
   *
   * Compares two Scope objects for equality.
   *
   * @access public
   * @since 1.0.0
   *
   * @param self $other The other Scope object to compare.
   *
   * @return bool True if the objects are equal, false otherwise.
   */
  public function equals(self $other): bool
  {
    return $this->value === $other->value;
  }

  /**
   * Method __toString
   *
   * Returns the string representation of the Scope object.
   *
   * @access public
   * @since 1.0.0
   *
   * @return string The string representation of the Scope object.
   */
  public function __toString(): string
  {
    return $this->value;
  }
  //#endregion
}
