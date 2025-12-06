<?php

declare(strict_types=1);

namespace Auth\Infrastructure\Security\User;

use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * User SecurityUser
 * @final
 *
 * Symfony Security User adapter.
 *
 * @category User
 * @package Auth\Infrastructure\Security\User
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class SecurityUser implements UserInterface, PasswordAuthenticatedUserInterface
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initialize the SecurityUser object.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $id The user ID.
   * @param string $email The user email.
   * @param string $password The hashed password.
   * @param list<string> $roles The user roles.
   * @param list<string> $scopes The OAuth2 scopes.
   * @param bool $isActive Whether the user is active.
   */
  public function __construct(
    private string $id,
    private string $email,
    private string $password,
    private array $roles = ['ROLE_USER'],
    private array $scopes = [],
    private bool $isActive = true
  ) {}
  //#endregion

  //#region Methods
  /**
   * Method getId
   *
   * Get the user ID.
   *
   * @access public
   * @since 1.0.0
   *
   * @return string The user ID.
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
   * @access public
   * @since 1.0.0
   *
   * @return string The user identifier.
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
   * @access public
   * @since 1.0.0
   *
   * @return list<string> The user roles.
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
   * Method getScopes
   *
   * Get the OAuth2 scopes.
   *
   * @access public
   * @since 1.0.0
   *
   * @return list<string> The OAuth2 scopes.
   */
  public function getScopes(): array
  {
    return $this->scopes;
  }

  /**
   * Method hasScope
   *
   * Check if the user has a specific scope.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $scope The scope to check.
   *
   * @return bool True if the user has the scope, false otherwise.
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
   * @access public
   * @since 1.0.0
   *
   * @return string The hashed password.
   */
  public function getPassword(): string
  {
    return $this->password;
  }

  /**
   * Method isActive
   *
   * Check if the user is active.
   *
   * @access public
   * @since 1.0.0
   *
   * @return bool True if the user is active, false otherwise.
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
   * @access public
   * @since 1.0.0
   *
   * @return void No return value.
   */
  public function eraseCredentials(): void
  {
    // No sensitive data to erase (class is readonly, password is already hashed)
  }
  //#endregion
}
