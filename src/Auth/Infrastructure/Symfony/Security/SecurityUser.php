<?php

declare(strict_types=1);

namespace Auth\Infrastructure\Symfony\Security;

use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Adapter SecurityUser
 * @final
 *
 * Symfony Security User adapter.
 * Wraps domain user data for Symfony Security component.
 *
 * @category Security
 * @package Auth\Infrastructure\Symfony\Security
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
   * Initializes a new instance of the SecurityUser class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $id The user ID.
   * @param string $email The user email (used as identifier).
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
  ) {
  }
  //#endregion

  //#region Methods
  /**
   * Method getId
   *
   * Returns the user ID.
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
   * Returns the identifier for this user (e.g. email).
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
   * Returns the roles granted to the user.
   *
   * @access public
   * @since 1.0.0
   *
   * @return list<string> The user roles.
   */
  public function getRoles(): array
  {
    $roles = $this->roles;

    // Guarantee every user at least has ROLE_USER
    if (!in_array('ROLE_USER', $roles, true)) {
      $roles[] = 'ROLE_USER';
    }

    return array_values(array_unique($roles));
  }

  /**
   * Method getScopes
   *
   * Returns the OAuth2 scopes granted to the user.
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
   * Checks if the user has a specific OAuth2 scope.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $scope The scope to check.
   *
   * @return bool True if the user has the scope.
   */
  public function hasScope(string $scope): bool
  {
    return in_array(strtolower($scope), array_map('strtolower', $this->scopes), true);
  }

  /**
   * Method getPassword
   * {@inheritDoc}
   *
   * Returns the hashed password.
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
   * Checks if the user is active.
   *
   * @access public
   * @since 1.0.0
   *
   * @return bool True if the user is active.
   */
  public function isActive(): bool
  {
    return $this->isActive;
  }

  /**
   * Method eraseCredentials
   * {@inheritDoc}
   *
   * Removes sensitive data from the user.
   *
   * @access public
   * @since 1.0.0
   *
   * @return void
   */
  public function eraseCredentials(): void
  {
    // No sensitive data to erase (password is already hashed)
  }
  //#endregion
}
