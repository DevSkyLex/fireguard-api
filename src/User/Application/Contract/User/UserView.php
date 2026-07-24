<?php

declare(strict_types=1);

namespace User\Application\Contract\User;

use DateTimeImmutable;

/**
 * Contract UserView.
 *
 * Read model exposed outside the User module.
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UserView
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $id the user ID
   * @param string $username the username
   * @param string $email the user email
   * @param string $firstName the first name
   * @param string $lastName the last name
   * @param string|null $avatarUrl the avatar URL
   * @param string $status the user status value
   * @param bool $emailVerified whether the email is verified
   * @param string|null $tenantId the tenant ID
   * @param DateTimeImmutable $createdAt when the user was created
   * @param DateTimeImmutable|null $lastLoginAt last login time (if any)
   * @param bool $canLogin whether the user can login
   * @param string $locale the preferred display language ("system" follows the browser)
   */
  public function __construct(
    public string $id,
    public string $username,
    public string $email,
    public string $firstName,
    public string $lastName,
    public ?string $avatarUrl,
    public string $status,
    public bool $emailVerified,
    public ?string $tenantId,
    public DateTimeImmutable $createdAt,
    public ?DateTimeImmutable $lastLoginAt,
    public bool $canLogin,
    public string $locale = 'system',
  ) {
  }
  // #endregion
}
