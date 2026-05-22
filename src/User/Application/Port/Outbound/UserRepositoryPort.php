<?php

declare(strict_types=1);

namespace User\Application\Port\Outbound;

use Shared\Application\Contract\Sorting\Sorting;
use Shared\Domain\ValueObject\Email;
use User\Domain\Model\User\User;
use User\Domain\ValueObject\{UserId, Username};

/**
 * Interface UserRepositoryPort.
 *
 * Port for user repository operations.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface UserRepositoryPort
{
  // #region Methods
  /**
   * Method save.
   *
   * Saves a user.
   *
   * @since 1.0.0
   *
   * @param User $user the user to save
   */
  public function save(User $user): void;

  /**
   * Method findById.
   *
   * Finds a user by ID.
   *
   * @since 1.0.0
   *
   * @param UserId $id the user ID
   *
   * @return User|null the user or null if not found
   */
  public function findById(UserId $id): ?User;

  /**
   * Method findByUsername.
   *
   * Finds a user by username.
   *
   * @since 1.0.0
   *
   * @param Username $username the username
   *
   * @return User|null the user or null if not found
   */
  public function findByUsername(Username $username): ?User;

  /**
   * Method findByEmail.
   *
   * Finds a user by email.
   *
   * @since 1.0.0
   *
   * @param Email $email the email
   *
   * @return User|null the user or null if not found
   */
  public function findByEmail(Email $email): ?User;

  /**
   * Method existsByUsername.
   *
   * Checks if a user exists with the given username.
   *
   * @since 1.0.0
   *
   * @param Username $username the username
   *
   * @return bool true if exists, false otherwise
   */
  public function existsByUsername(Username $username): bool;

  /**
   * Method existsByEmail.
   *
   * Checks if a user exists with the given email.
   *
   * @since 1.0.0
   *
   * @param Email $email the email
   *
   * @return bool true if exists, false otherwise
   */
  public function existsByEmail(Email $email): bool;

  /**
   * Method delete.
   *
   * Deletes a user.
   *
   * @since 1.0.0
   *
   * @param User $user the user to delete
   */
  public function delete(User $user): void;

  /**
   * Method findAll.
   *
   * Finds all users.
   *
   * @since 1.0.0
   *
   * @return array<User> the list of users
   */
  public function findAll(): array;

  /**
   * @return list<User>
   */
  public function findFiltered(?string $search, Sorting $sorting, int $limit, int $offset, ?string $tenantId = null): array;

  public function countFiltered(?string $search, ?string $tenantId = null): int;
  // #endregion
}
