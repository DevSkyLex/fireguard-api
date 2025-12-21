<?php

declare(strict_types=1);

namespace User\Domain\ValueObject;

use Shared\Domain\Exception\InvalidValueException;

use function filter_var;
use function mb_strlen;
use function sprintf;

/**
 * ValueObject UserProfile.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UserProfile
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the UserProfile class.
   *
   * @since 1.0.0
   *
   * @param string      $firstName the user's first name
   * @param string      $lastName  the user's last name
   * @param string|null $avatarUrl optional URL to the user's avatar image
   *
   * @throws InvalidValueException if the profile data is invalid
   */
  public function __construct(
    public string $firstName,
    public string $lastName,
    public ?string $avatarUrl = null,
  ) {
    if ('' === $firstName || '' === $lastName) {
      throw InvalidValueException::because(
        message: 'First name and last name cannot be empty.'
      );
    }

    if (mb_strlen($firstName) > 100 || mb_strlen($lastName) > 100) {
      throw InvalidValueException::because(
        message: 'First name and last name must be 100 characters or less.'
      );
    }

    if (null !== $avatarUrl && !filter_var($avatarUrl, FILTER_VALIDATE_URL)) {
      throw InvalidValueException::because(
        message: 'Avatar URL must be a valid URL.'
      );
    }
  }
  // #endregion

  // #region Methods
  /**
   * Method fullName.
   *
   * Returns the user's full name.
   *
   * @since 1.0.0
   *
   * @return string the full name (firstName lastName)
   */
  public function fullName(): string
  {
    return sprintf('%s %s', $this->firstName, $this->lastName);
  }

  /**
   * Method equals.
   *
   * Compares two UserProfile objects for equality.
   *
   * @since 1.0.0
   *
   * @param self $other the other UserProfile object to compare
   *
   * @return bool true if the objects are equal, false otherwise
   */
  public function equals(self $other): bool
  {
    return $this->firstName === $other->firstName
      && $this->lastName === $other->lastName
      && $this->avatarUrl === $other->avatarUrl;
  }
  // #endregion
}
