<?php

declare(strict_types=1);

namespace OAuth\Application\Service;

use User\Domain\Model\User;

use function array_map;
use function in_array;
use function strtolower;

/**
 * Service OidcClaimsBuilder.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OidcClaimsBuilder implements OidcClaimsBuilderInterface
{
  // #region Methods
  /**
   * Method buildUserInfoClaims.
   *
   * Builds the OpenID Connect UserInfo
   * claims for a user.
   *
   * @since 1.0.0
   *
   * @param User $user the user
   * @param list<string> $scopes the granted scopes
   *
   * @return array<string, mixed> the user info claims
   */
  public function buildUserInfoClaims(User $user, array $scopes): array
  {
    return $this->buildClaims(user: $user, scopes: $scopes, includeAuthTime: false);
  }

  /**
   * Method buildIdTokenClaims.
   *
   * Builds the OpenID Connect ID token
   * claims for a user.
   *
   * @since 1.0.0
   *
   * @param User $user the user
   * @param list<string> $scopes the granted scopes
   *
   * @return array<string, mixed> the ID token claims
   */
  public function buildIdTokenClaims(User $user, array $scopes): array
  {
    return $this->buildClaims(user: $user, scopes: $scopes, includeAuthTime: true);
  }

  /**
   * Method buildClaims.
   *
   * Builds the OpenID Connect claims for a user.
   *
   * @since 1.0.0
   *
   * @param User $user the user
   * @param list<string> $scopes the granted scopes
   * @param bool $includeAuthTime whether to include the authentication time
   *
   * @return array<string, mixed> the claims
   */
  private function buildClaims(User $user, array $scopes, bool $includeAuthTime): array
  {
    $normalizedScopes = $this->normalizeScopes($scopes);

    $claims = [
      'sub' => $user->id()->value,
    ];

    if ($this->hasScope($normalizedScopes, 'profile')) {
      $profile = $user->profile();
      $claims['name'] = $profile->fullName();
      $claims['given_name'] = $profile->firstName;
      $claims['family_name'] = $profile->lastName;
      $claims['preferred_username'] = (string) $user->username();

      if (null !== $profile->avatarUrl && '' !== $profile->avatarUrl) {
        $claims['picture'] = $profile->avatarUrl;
      }
    }

    if ($this->hasScope($normalizedScopes, 'email')) {
      $claims['email'] = (string) $user->email();
      $claims['email_verified'] = $user->isEmailVerified();
    }

    if ($includeAuthTime) {
      $authTime = $user->lastLoginAt();
      if (null !== $authTime) {
        $claims['auth_time'] = $authTime->getTimestamp();
      }
    }

    return $claims;
  }

  /**
   * Method normalizeScopes.
   *
   * Normalizes the scopes to lowercase.
   *
   * @since 1.0.0
   *
   * @param list<string> $scopes the scopes
   *
   * @return list<string> the normalized scopes
   */
  private function normalizeScopes(array $scopes): array
  {
    return array_map(
      static fn (string $scope): string => strtolower($scope),
      $scopes,
    );
  }

  /**
   * Method hasScope.
   *
   * Checks if the given scope is in the list of scopes.
   *
   * @since 1.0.0
   *
   * @param list<string> $scopes the scopes
   * @param string $scope the scope to check
   *
   * @return bool whether the scope is in the list
   */
  private function hasScope(array $scopes, string $scope): bool
  {
    return in_array(strtolower($scope), $scopes, true);
  }
  // #endregion
}
