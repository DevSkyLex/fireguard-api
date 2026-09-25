<?php

declare(strict_types=1);

namespace User\Infrastructure\Persistence\Doctrine\Mapper;

use Shared\Domain\ValueObject\{Email, TenantId};
use User\Domain\Model\User\{RestoredUserActivity, RestoredUserIdentity, RestoredUserSecurity, User};
use User\Domain\ValueObject\{HashedPassword, Locale, UserId, UserProfile, UserStatus, Username};
use User\Infrastructure\Persistence\Doctrine\Record\UserRecord;

/**
 * Mapper UserMapper.
 *
 * @category Mapper
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class UserMapper
{
  // #region Methods
  /**
   * Method toRecord.
   *
   * Converts a User domain model to a
   * UserRecord persistence model.
   *
   * @since 1.0.0
   *
   * @param User $user the domain model
   *
   * @return UserRecord the persistence model
   */
  public function toRecord(User $user): UserRecord
  {
    $record = new UserRecord();
    $record->id = $user->id()->value;
    $record->username = $user->username()->value;
    $record->email = $user->email()->value;
    $record->firstName = $user->profile()->firstName;
    $record->lastName = $user->profile()->lastName;
    $record->avatarUrl = $user->profile()->avatarUrl;
    $record->status = $user->status()->value;
    $record->emailVerified = $user->isEmailVerified();
    $record->emailOwnershipVerifiedAt = $user->emailOwnershipVerifiedAt();
    $record->tenantId = $user->tenantId()?->__toString();
    $record->createdAt = $user->createdAt();
    $record->lastLoginAt = $user->lastLoginAt();
    $record->lastSignInMethod = $user->lastSignInMethod();
    $record->locale = $user->locale()->value;

    $record->password = $user->hashedPassword()?->value;
    $record->failedLoginAttempts = $user->failedLoginAttempts();

    return $record;
  }

  /**
   * Method updateRecord.
   *
   * Updates an existing UserRecord with data from
   * a User domain model.
   *
   * @since 1.0.0
   *
   * @param UserRecord $record the existing persistence model
   * @param User $user the domain model
   */
  public function updateRecord(UserRecord $record, User $user): void
  {
    $record->username = $user->username()->value;
    $record->email = $user->email()->value;
    $record->firstName = $user->profile()->firstName;
    $record->lastName = $user->profile()->lastName;
    $record->avatarUrl = $user->profile()->avatarUrl;
    $record->status = $user->status()->value;
    $record->emailVerified = $user->isEmailVerified();
    $record->emailOwnershipVerifiedAt = $user->emailOwnershipVerifiedAt();
    $record->tenantId = $user->tenantId()?->__toString();
    $record->lastLoginAt = $user->lastLoginAt();
    $record->lastSignInMethod = $user->lastSignInMethod();
    $record->locale = $user->locale()->value;

    $record->password = $user->hashedPassword()?->value;
    $record->failedLoginAttempts = $user->failedLoginAttempts();
  }

  /**
   * Method toDomain.
   *
   * Converts a UserRecord persistence model
   * to a User domain model.
   *
   * @since 1.0.0
   *
   * @param UserRecord $record the persistence model
   *
   * @return User the domain model
   */
  public function toDomain(UserRecord $record): User
  {
    return User::restore(
      identity: new RestoredUserIdentity(
        id: new UserId($record->id),
        username: new Username($record->username),
        email: new Email($record->email),
        tenantId: $record->tenantId ? TenantId::fromString($record->tenantId) : null,
      ),
      profile: new UserProfile(
        firstName: $record->firstName,
        lastName: $record->lastName,
        avatarUrl: $record->avatarUrl,
      ),
      security: new RestoredUserSecurity(
        password: null === $record->password ? null : new HashedPassword($record->password),
        status: UserStatus::from($record->status),
        emailVerified: $record->emailVerified,
        failedLoginAttempts: $record->failedLoginAttempts,
        emailOwnershipVerifiedAt: $record->emailOwnershipVerifiedAt,
      ),
      activity: new RestoredUserActivity(
        createdAt: $record->createdAt,
        lastLoginAt: $record->lastLoginAt,
        lastSignInMethod: $record->lastSignInMethod,
        locale: Locale::from($record->locale),
      ),
    );
  }
  // #endregion
}
