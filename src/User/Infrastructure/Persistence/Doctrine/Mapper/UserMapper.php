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
use User\Infrastructure\Persistence\Doctrine\Entity\UserRecord;

/**
 * Mapper UserMapper
 * @final
 *
 * Maps between User domain model and UserRecord persistence model.
 *
 * @category Mapper
 * @package User\Infrastructure\Persistence\Doctrine\Mapper
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class UserMapper
{
  //#region Methods
  /**
   * Method toRecord
   *
   * Converts a User domain model to a 
   * UserRecord persistence model.
   * 
   * @access public
   * @since 1.0.0
   *
   * @param User $user The domain model.
   *
   * @return UserRecord The persistence model.
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
    $record->password = $password->value;

    // Access private failedLoginAttempts property via reflection
    $attemptsProperty = $reflection->getProperty('failedLoginAttempts');
    $attemptsProperty->setAccessible(true);
    $record->failedLoginAttempts = $attemptsProperty->getValue($user);

    return $record;
  }

  /**
   * Method toDomain
   *
   * Converts a UserRecord persistence model 
   * to a User domain model.
   * 
   * @access public
   * @since 1.0.0
   *
   * @param UserRecord $record The persistence model.
   *
   * @return User The domain model.
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
   * Method setProperty
   *
   * Sets a private property value using 
   * reflection.
   * 
   * @access private
   * @since 1.0.0
   *
   * @param object $object The object.
   * @param string $propertyName The property name.
   * @param mixed $value The value to set.
   *
   * @return void
   */
  private function setProperty(object $object, string $propertyName, mixed $value): void
  {
    $reflection = new ReflectionClass($object);
    $property = $reflection->getProperty($propertyName);
    $property->setAccessible(true);
    $property->setValue($object, $value);
  }
  //#endregion
}
