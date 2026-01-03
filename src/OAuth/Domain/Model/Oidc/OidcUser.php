<?php

declare(strict_types=1);

namespace OAuth\Domain\Model\Oidc;

use DateTimeImmutable;
use Shared\Domain\Exception\InvalidValueException;

use function trim;

/**
 * Model OidcUser.
 *
 * Represents the minimum identity data required to
 * build OpenID Connect claims.
 *
 * @category Model
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OidcUser
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of
   * the OidcUser class.
   *
   * @since 1.0.0
   *
   * @param string $subject the OIDC subject (sub)
   * @param string|null $preferredUsername preferred username (optional)
   * @param string|null $email the user email (optional)
   * @param bool $emailVerified whether email is verified
   * @param string|null $givenName the given name (optional)
   * @param string|null $familyName the family name (optional)
   * @param string|null $pictureUrl the profile picture URL (optional)
   * @param DateTimeImmutable|null $authTime authentication time (optional)
   *
   * @throws InvalidValueException if the subject is empty
   */
  public function __construct(
    private string $subject,
    private ?string $preferredUsername = null,
    private ?string $email = null,
    private bool $emailVerified = false,
    private ?string $givenName = null,
    private ?string $familyName = null,
    private ?string $pictureUrl = null,
    private ?DateTimeImmutable $authTime = null,
  ) {
    if ('' === trim($subject)) {
      throw InvalidValueException::because(
        message: 'OIDC subject cannot be empty.',
      );
    }
  }
  // #endregion

  // #region Methods
  /**
   * Method subject.
   *
   * Returns the OIDC subject identifier.
   *
   * @since 1.0.0
   *
   * @return string the subject identifier
   */
  public function subject(): string
  {
    return $this->subject;
  }

  /**
   * Method preferredUsername.
   *
   * Returns the preferred username.
   *
   * @since 1.0.0
   *
   * @return string|null the preferred username
   */
  public function preferredUsername(): ?string
  {
    return $this->preferredUsername;
  }

  /**
   * Method email.
   *
   * Returns the email address.
   *
   * @since 1.0.0
   *
   * @return string|null the email address
   */
  public function email(): ?string
  {
    return $this->email;
  }

  /**
   * Method emailVerified.
   *
   * Indicates if the email is verified.
   *
   * @since 1.0.0
   *
   * @return bool true if verified, false otherwise
   */
  public function emailVerified(): bool
  {
    return $this->emailVerified;
  }

  /**
   * Method givenName.
   *
   * Returns the given name.
   *
   * @since 1.0.0
   *
   * @return string|null the given name
   */
  public function givenName(): ?string
  {
    return $this->givenName;
  }

  /**
   * Method familyName.
   *
   * Returns the family name.
   *
   * @since 1.0.0
   *
   * @return string|null the family name
   */
  public function familyName(): ?string
  {
    return $this->familyName;
  }

  /**
   * Method pictureUrl.
   *
   * Returns the profile picture URL.
   *
   * @since 1.0.0
   *
   * @return string|null the picture URL
   */
  public function pictureUrl(): ?string
  {
    return $this->pictureUrl;
  }

  /**
   * Method authTime.
   *
   * Returns the authentication time.
   *
   * @since 1.0.0
   *
   * @return DateTimeImmutable|null the auth time
   */
  public function authTime(): ?DateTimeImmutable
  {
    return $this->authTime;
  }
  // #endregion
}
