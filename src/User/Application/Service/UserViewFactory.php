<?php

declare(strict_types=1);

namespace User\Application\Service;

use User\Application\Contract\User\UserView;
use User\Domain\Model\User\User;

/**
 * Deterministic owner-local account projection.
 *
 * @category Service
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class UserViewFactory
{
  public static function fromUser(User $user): UserView
  {
    return new UserView(
      id: $user->id()->value,
      username: $user->username()->value,
      email: $user->email()->value,
      firstName: $user->profile()->firstName,
      lastName: $user->profile()->lastName,
      avatarUrl: $user->profile()->avatarUrl,
      status: $user->status()->value,
      emailVerified: $user->isEmailVerified(),
      tenantId: $user->tenantId()?->__toString(),
      createdAt: $user->createdAt(),
      lastLoginAt: $user->lastLoginAt(),
      canLogin: $user->canLogin(),
      locale: $user->locale()->value,
    );
  }
}
