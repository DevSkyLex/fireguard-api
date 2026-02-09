<?php

declare(strict_types=1);

namespace OAuth\Infrastructure\OAuth2\League\Repository;

use League\OAuth2\Server\Entities\{ClientEntityInterface, ScopeEntityInterface};
use League\OAuth2\Server\Repositories\ScopeRepositoryInterface;
use OAuth\Domain\ValueObject\Scope\Scope;
use OAuth\Infrastructure\OAuth2\League\Entity\Scope as LeagueScope;

use function is_string;
use function trim;

/**
 * Repository ScopeRepositoryAdapter.
 *
 * @category Repository
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ScopeRepositoryAdapter implements ScopeRepositoryInterface
{
  // #region Methods
  /**
   * Method getScopeEntityByIdentifier
   * {@inheritDoc}
   *
   * Resolve a scope entity by its identifier.
   *
   * @since 1.0.0
   *
   * @param mixed $identifier the scope identifier
   *
   * @return LeagueScope|null the scope entity
   */
  public function getScopeEntityByIdentifier(mixed $identifier): ?LeagueScope
  {
    if (!is_string($identifier)) {
      return null;
    }

    $normalized = trim($identifier);
    if ('' === $normalized) {
      return null;
    }

    $domainScope = Scope::tryFrom(value: $normalized);
    if (null === $domainScope) {
      return null;
    }

    $scope = new LeagueScope();
    $scope->setIdentifier(identifier: $normalized);

    return $scope;
  }

  /**
   * Method finalizeScopes
   * {@inheritDoc}
   *
   * Finalize the scopes for the grant request.
   *
   * @since 1.0.0
   *
   * @param array<int, ScopeEntityInterface> $scopes the scopes
   * @param string $grantType the grant type
   * @param ClientEntityInterface $clientEntity the client entity
   * @param string|int|null $userIdentifier the user identifier
   * @param string|null $authCodeId the auth code identifier
   *
   * @return array<int, ScopeEntityInterface> the finalized scopes
   */
  public function finalizeScopes(
    array $scopes,
    string $grantType,
    ClientEntityInterface $clientEntity,
    string|int|null $userIdentifier = null,
    ?string $authCodeId = null,
  ): array {
    return $scopes;
  }
  // #endregion
}
