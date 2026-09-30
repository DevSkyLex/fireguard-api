<?php

declare(strict_types=1);

namespace User\Domain\Model\User;

use DateTimeImmutable;
use User\Domain\ValueObject\{HashedPassword, UserStatus};

/**
 * Authentication and mailbox state restored from a previously persisted user.
 *
 * @category Model
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RestoredUserSecurity
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Carries persisted authentication and verification state without raw credentials.
   *
   * @access public
   *
   * @param ?HashedPassword $password optional password hash
   * @param UserStatus $status current account lifecycle status
   * @param bool $emailVerified whether the account email is verified
   * @param int $failedLoginAttempts number of consecutive failed login attempts
   * @param ?DateTimeImmutable $emailOwnershipVerifiedAt optional time when email ownership was verified
   *
   * @return void
   */
  public function __construct(
    public ?HashedPassword $password,
    public UserStatus $status,
    public bool $emailVerified,
    public int $failedLoginAttempts,
    public ?DateTimeImmutable $emailOwnershipVerifiedAt,
  ) {
  }
  // #endregion
}
