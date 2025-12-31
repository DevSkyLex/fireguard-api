<?php

declare(strict_types=1);

namespace OAuth\Application\Service;

use User\Domain\Model\User;

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
   * @param User $user the user
   * @param list<string> $scopes the granted scopes
   *
   * @return array<string, mixed> the user info claims
   */
  public function buildUserInfoClaims(User $user, array $scopes): array;

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
  public function buildIdTokenClaims(User $user, array $scopes): array;
}
