<?php

declare(strict_types=1);

namespace User\Infrastructure\Persistence\Doctrine\Mapper;

use ReflectionClass;
use Shared\Domain\ValueObject\Email;
use Shared\Domain\ValueObject\TenantId;
use User\Domain\Model\User;
use User\Domain\ValueObject\HashedPassword;
use User\Domain\ValueObject\UserId;
use User\Domain\ValueObject\Username;
use User\Domain\ValueObject\UserProfile;
use User\Domain\ValueObject\UserStatus;
use User\Infrastructure\Persistence\Doctrine\Record\UserRecord;

use function is_int;

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
    $record->tenantId = $user->tenantId()?->__toString();
    $record->createdAt = $user->createdAt();
    $record->lastLoginAt = $user->lastLoginAt();

    // Access private password property via reflection
    $reflection = new ReflectionClass($user);
    $passwordProperty = $reflection->getProperty('password');
    $passwordProperty->setAccessible(true);
    $password = $passwordProperty->getValue($user);
    $record->password = ($password instanceof HashedPassword) ? $password->value : '';

    // Access private failedLoginAttempts property via reflection
    $attemptsProperty = $reflection->getProperty('failedLoginAttempts');
    $attemptsProperty->setAccessible(true);
    $attempts = $attemptsProperty->getValue($user);
    $record->failedLoginAttempts = is_int($attempts) ? $attempts : 0;

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
   * @param User       $user   the domain model
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
    $record->tenantId = $user->tenantId()?->__toString();
    $record->lastLoginAt = $user->lastLoginAt();

    // Access private password property via reflection
    $reflection = new ReflectionClass($user);
    $passwordProperty = $reflection->getProperty('password');
    $passwordProperty->setAccessible(true);
    $password = $passwordProperty->getValue($user);
    $record->password = ($password instanceof HashedPassword) ? $password->value : '';

    // Access private failedLoginAttempts property via reflection
    $attemptsProperty = $reflection->getProperty('failedLoginAttempts');
    $attemptsProperty->setAccessible(true);
    $attempts = $attemptsProperty->getValue($user);
    $record->failedLoginAttempts = is_int($attempts) ? $attempts : 0;
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
    // Create User using reflection to bypass private constructor
    $reflection = new ReflectionClass(User::class);
    $user = $reflection->newInstanceWithoutConstructor();

    // Set properties via reflection
    $this->setProperty($user, 'id', new UserId($record->id));
    $this->setProperty($user, 'username', new Username($record->username));
    $this->setProperty($user, 'email', new Email($record->email));
    $this->setProperty($user, 'password', new HashedPassword($record->password));
    $this->setProperty($user, 'profile', new UserProfile(
      firstName: $record->firstName,
      lastName: $record->lastName,
      avatarUrl: $record->avatarUrl,
    ));
    $this->setProperty($user, 'status', UserStatus::from($record->status));
    $this->setProperty($user, 'emailVerified', $record->emailVerified);
    $this->setProperty($user, 'tenantId', $record->tenantId ? TenantId::fromString($record->tenantId) : null);
    $this->setProperty($user, 'createdAt', $record->createdAt);
    $this->setProperty($user, 'lastLoginAt', $record->lastLoginAt);
    $this->setProperty($user, 'failedLoginAttempts', $record->failedLoginAttempts);

    return $user;
  }

  /**
   * Method setProperty.
   *
   * Sets a private property value using
   * reflection.
   *
   * @since 1.0.0
   *
   * @param object $object       the object
   * @param string $propertyName the property name
   * @param mixed  $value        the value to set
   */
  private function setProperty(object $object, string $propertyName, mixed $value): void
  {
    $reflection = new ReflectionClass($object);
    $property = $reflection->getProperty($propertyName);
    $property->setAccessible(true);
    $property->setValue($object, $value);
  }
  // #endregion
}
