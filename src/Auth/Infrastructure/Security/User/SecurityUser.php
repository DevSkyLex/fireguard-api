<?php

declare(strict_types=1);

namespace Auth\Infrastructure\Security\User;

use Symfony\Component\Security\Core\User\{PasswordAuthenticatedUserInterface, UserInterface};

use function array_map;
use function array_unique;
use function array_values;
use function in_array;
use function strtolower;

/**
 * User SecurityUser.
 *
 * @category User
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class SecurityUser implements UserInterface, PasswordAuthenticatedUserInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initialize the SecurityUser object.
   *
   * @since 1.0.0
   *
   * @param string $id the user ID
   * @param string $email the user email
   * @param string $password the hashed password
   * @param list<string> $roles the user roles
   * @param list<string> $scopes the OAuth2 scopes
   * @param bool $isActive whether the user is active
   */
  public function __construct(
    private string $id,
    private string $email,
    private string $password,
    private array $roles = ['ROLE_USER'],
    private array $scopes = [],
    private bool $isActive = true,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method getId.
   *
   * Get the user ID.
   *
   * @since 1.0.0
   *
   * @return string the user ID
   */
  public function getId(): string
  {
    return $this->id;
  }

  /**
   * Method getUserIdentifier
   * {@inheritDoc}
   *
   * Get the user identifier.
   *
   * @since 1.0.0
   *
   * @return string the user identifier
   */
  public function getUserIdentifier(): string
  {
    return $this->email;
  }

  /**
   * Method getRoles
   * {@inheritDoc}
   *
   * Get the user roles.
   *
   * @since 1.0.0
   *
   * @return list<string> the user roles
   */
  public function getRoles(): array
  {
    $roles = $this->roles;

    if (!in_array('ROLE_USER', $roles, true)) {
      $roles[] = 'ROLE_USER';
    }

    return array_values(array_unique($roles));
  }

  /**
   * Method getScopes.
   *
   * Get the OAuth2 scopes.
   *
   * @since 1.0.0
   *
   * @return list<string> the OAuth2 scopes
   */
  public function getScopes(): array
  {
    return $this->scopes;
  }

  /**
   * Method hasScope.
   *
   * Check if the user has a specific scope.
   *
   * @since 1.0.0
   *
   * @param string $scope the scope to check
   *
   * @return bool true if the user has the scope, false otherwise
   */
  public function hasScope(string $scope): bool
  {
    return in_array(strtolower($scope), array_map('strtolower', $this->scopes), true);
  }

  /**
   * Method getPassword
   * {@inheritDoc}
   *
   * Get the hashed password.
   *
   * @since 1.0.0
   *
   * @return string the hashed password
   */
  public function getPassword(): string
  {
    return $this->password;
  }

  /**
   * Method isActive.
   *
   * Check if the user is active.
   *
   * @since 1.0.0
   *
   * @return bool true if the user is active, false otherwise
   */
  public function isActive(): bool
  {
    return $this->isActive;
  }

  /**
   * Method eraseCredentials
   * {@inheritDoc}
   *
   * Erase sensitive data from the user object.
   *
   * @since 1.0.0
   *
   * @return void no return value
   */
  public function eraseCredentials(): void
  {
    // No sensitive data to erase (class is readonly, password is already hashed)
  }
  // #endregion
}
