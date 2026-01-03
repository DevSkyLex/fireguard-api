<?php

declare(strict_types=1);

namespace OAuth\Application\Service;

use OAuth\Domain\Model\Oidc\OidcUser;

use function array_map;
use function in_array;
use function is_string;
use function strtolower;
use function trim;

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
   * @param OidcUser $user the OIDC user
   * @param list<string> $scopes the granted scopes
   *
   * @return array<string, mixed> the user info claims
   */
  public function buildUserInfoClaims(OidcUser $user, array $scopes): array
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
   * @param OidcUser $user the OIDC user
   * @param list<string> $scopes the granted scopes
   *
   * @return array<string, mixed> the ID token claims
   */
  public function buildIdTokenClaims(OidcUser $user, array $scopes): array
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
   * @param OidcUser $user the OIDC user
   * @param list<string> $scopes the granted scopes
   * @param bool $includeAuthTime whether to include the authentication time
   *
   * @return array<string, mixed> the claims
   */
  private function buildClaims(OidcUser $user, array $scopes, bool $includeAuthTime): array
  {
    $normalizedScopes = $this->normalizeScopes($scopes);

    $claims = [
      'sub' => $user->subject(),
    ];

    if ($this->hasScope($normalizedScopes, 'profile')) {
      $name = $this->buildFullName($user->givenName(), $user->familyName());
      if (null !== $name) {
        $claims['name'] = $name;
      }

      $givenName = $this->normalizeText($user->givenName());
      if (null !== $givenName) {
        $claims['given_name'] = $givenName;
      }

      $familyName = $this->normalizeText($user->familyName());
      if (null !== $familyName) {
        $claims['family_name'] = $familyName;
      }

      $preferredUsername = $this->normalizeText($user->preferredUsername());
      if (null !== $preferredUsername) {
        $claims['preferred_username'] = $preferredUsername;
      }

      $picture = $this->normalizeText($user->pictureUrl());
      if (null !== $picture) {
        $claims['picture'] = $picture;
      }
    }

    if ($this->hasScope($normalizedScopes, 'email')) {
      $email = $this->normalizeText($user->email());
      if (null !== $email) {
        $claims['email'] = $email;
        $claims['email_verified'] = $user->emailVerified();
      }
    }

    if ($includeAuthTime) {
      $authTime = $user->authTime();
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

  private function buildFullName(?string $givenName, ?string $familyName): ?string
  {
    $given = $this->normalizeText($givenName);
    $family = $this->normalizeText($familyName);

    if (null === $given && null === $family) {
      return null;
    }

    return trim(trim((string) $given . ' ' . (string) $family));
  }

  private function normalizeText(?string $value): ?string
  {
    if (!is_string($value)) {
      return null;
    }

    $normalized = trim($value);
    if ('' === $normalized) {
      return null;
    }

    return $normalized;
  }
  // #endregion
}
