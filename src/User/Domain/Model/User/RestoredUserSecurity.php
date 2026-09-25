<?php

declare(strict_types=1);

namespace User\Domain\Model\User;

use DateTimeImmutable;
use User\Domain\ValueObject\{HashedPassword, UserStatus};

/**
 * Authentication and mailbox state restored from a previously persisted user.
 *
 * @category Model
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RestoredUserSecurity
{
  public function __construct(
    public ?HashedPassword $password,
    public UserStatus $status,
    public bool $emailVerified,
    public int $failedLoginAttempts,
    public ?DateTimeImmutable $emailOwnershipVerifiedAt,
  ) {
  }
}
