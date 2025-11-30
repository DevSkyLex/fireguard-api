<?php

declare(strict_types=1);

namespace User\Application\Port\Outbound;

use User\Domain\Model\User;
use User\Domain\ValueObject\UserId;
use User\Domain\ValueObject\Username;
use Shared\Domain\ValueObject\Email;

/**
 * Interface UserRepositoryPort
 *
 * Port for user repository operations.
 *
 * @category Port
 * @package User\Application\Port\Outbound
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface UserRepositoryPort
{
  //#region Methods
  /**
   * Method save
   *
   * Saves a user.
   * 
   * @access public
   * @since 1.0.0
   *
   * @param User $user The user to save.
   *
   * @return void
   */
  public function save(User $user): void;

  /**
   * Method findById
   *
   * Finds a user by ID.
   * 
   * @access public
   * @since 1.0.0
   *
   * @param UserId $id The user ID.
   *
   * @return User|null The user or null if not found.
   */
  public function findById(UserId $id): ?User;

  /**
   * Method findByUsername
   *
   * Finds a user by username.
   * 
   * @access public
   * @since 1.0.0
   *
   * @param Username $username The username.
   *
   * @return User|null The user or null if not found.
   */
  public function findByUsername(Username $username): ?User;

  /**
   * Method findByEmail
   *
   * Finds a user by email.
   * 
   * @access public
   * @since 1.0.0
   *
   * @param Email $email The email.
   *
   * @return User|null The user or null if not found.
   */
  public function findByEmail(Email $email): ?User;

  /**
   * Method existsByUsername
   *
   * Checks if a user exists with the given username.
   * 
   * @access public
   * @since 1.0.0
   *
   * @param Username $username The username.
   *
   * @return bool True if exists, false otherwise.
   */
  public function existsByUsername(Username $username): bool;

  /**
   * Method existsByEmail
   *
   * Checks if a user exists with the given email.
   * 
   * @access public
   * @since 1.0.0
   *
   * @param Email $email The email.
   *
   * @return bool True if exists, false otherwise.
   */
  public function existsByEmail(Email $email): bool;
  /**
   * Method delete
   *
   * Deletes a user.
   * 
   * @access public
   * @since 1.0.0
   *
   * @param User $user The user to delete.
   *
   * @return void
   */
  public function delete(User $user): void;

  /**
   * Method findAll
   *
   * Finds all users.
   * 
   * @access public
   * @since 1.0.0
   *
   * @return array<User> The list of users.
   */
  public function findAll(): array;
  //#endregion
}
