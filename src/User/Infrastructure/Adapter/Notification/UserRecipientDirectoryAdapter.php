<?php

declare(strict_types=1);

namespace User\Infrastructure\Adapter\Notification;

use Notification\Application\Port\Outbound\RecipientDirectoryPort;
use Shared\Domain\Exception\InvalidValueException;
use User\Application\Port\Outbound\UserRepositoryPort;
use User\Domain\ValueObject\UserId;

/**
 * Adapter UserRecipientDirectoryAdapter.
 *
 * Implements the Notification module's recipient directory on top of the User
 * repository: notifications addressed by `recipientUserId` resolve to the
 * account's email address without the calling module knowing about users.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UserRecipientDirectoryAdapter implements RecipientDirectoryPort
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the UserRecipientDirectoryAdapter class.
   *
   * @since 1.0.0
   *
   * @param UserRepositoryPort $users the user repository port
   */
  public function __construct(
    private UserRepositoryPort $users,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method emailForUserId.
   *
   * Returns the email address of a user, or null when the identifier is
   * malformed or the user does not exist.
   *
   * @since 1.0.0
   *
   * @param string $userId the platform user identifier
   *
   * @return ?string the email address, or null when unresolvable
   */
  public function emailForUserId(string $userId): ?string
  {
    try {
      $user = $this->users->findById(new UserId($userId));
    } catch (InvalidValueException) {
      return null;
    }

    return null !== $user ? (string) $user->email() : null;
  }
  // #endregion
}
