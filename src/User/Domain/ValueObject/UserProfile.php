<?php

declare(strict_types=1);

namespace User\Domain\ValueObject;

use Shared\Domain\Exception\InvalidValueException;

use function mb_strlen;
use function filter_var;
use function sprintf;

/**
 * ValueObject UserProfile
 * @final
 *
 * Represents a user's profile information.
 *
 * @category ValueObject
 * @package User\Domain\ValueObject
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UserProfile
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the UserProfile class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $firstName The user's first name.
   * @param string $lastName The user's last name.
   * @param string|null $avatarUrl Optional URL to the user's avatar image.
   *
   * @throws InvalidValueException If the profile data is invalid.
   */
  public function __construct(
    public string $firstName,
    public string $lastName,
    public ?string $avatarUrl = null,
  ) {
    if ($firstName === '' || $lastName === '') {
      throw InvalidValueException::because(
        message: 'First name and last name cannot be empty.'
      );
    }

    if (mb_strlen($firstName) > 100 || mb_strlen($lastName) > 100) {
      throw InvalidValueException::because(
        message: 'First name and last name must be 100 characters or less.'
      );
    }

    if ($avatarUrl !== null && !filter_var($avatarUrl, FILTER_VALIDATE_URL)) {
      throw InvalidValueException::because(
        message: 'Avatar URL must be a valid URL.'
      );
    }
  }
  //#endregion

  //#region Methods
  /**
   * Method fullName
   *
   * Returns the user's full name.
   *
   * @access public
   * @since 1.0.0
   *
   * @return string The full name (firstName lastName).
   */
  public function fullName(): string
  {
    return sprintf('%s %s', $this->firstName, $this->lastName);
  }

  /**
   * Method equals
   *
   * Compares two UserProfile objects for equality.
   *
   * @access public
   * @since 1.0.0
   *
   * @param self $other The other UserProfile object to compare.
   *
   * @return bool True if the objects are equal, false otherwise.
   */
  public function equals(self $other): bool
  {
    return $this->firstName === $other->firstName
      && $this->lastName === $other->lastName
      && $this->avatarUrl === $other->avatarUrl;
  }
  //#endregion
}
