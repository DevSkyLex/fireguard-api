<?php

declare(strict_types=1);

namespace Auth\Application\Contract\User;

/**
 * ValueObject UserAuthenticationResult.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UserAuthenticationResult
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param bool $authenticated whether authentication succeeded
   * @param string|null $userId the user identifier
   * @param string|null $email the user email
   */
  public function __construct(
    public bool $authenticated,
    public ?string $userId = null,
    public ?string $email = null,
  ) {
  }
  // #endregion

  // #region Factories
  /**
   * Method success.
   *
   * Creates a successful authentication result.
   *
   * @since 1.0.0
   *
   * @param string $userId the user identifier
   * @param string|null $email the user email
   *
   * @return self the result
   */
  public static function success(string $userId, ?string $email = null): self
  {
    return new self(
      authenticated: true,
      userId: $userId,
      email: $email,
    );
  }

  /**
   * Method failed.
   *
   * Creates a failed authentication result.
   *
   * @since 1.0.0
   *
   * @return self the result
   */
  public static function failed(): self
  {
    return new self(authenticated: false);
  }
  // #endregion
}
