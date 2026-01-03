<?php

declare(strict_types=1);

namespace OAuth\Application\Service;

use OAuth\Domain\Model\Oidc\OidcUser;

/**
 * Interface OidcClaimsBuilderInterface.
 *
 * Interface for building OIDC claims.
 *
 * @category Service Interface
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface OidcClaimsBuilderInterface
{
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
  public function buildUserInfoClaims(OidcUser $user, array $scopes): array;

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
  public function buildIdTokenClaims(OidcUser $user, array $scopes): array;
}
