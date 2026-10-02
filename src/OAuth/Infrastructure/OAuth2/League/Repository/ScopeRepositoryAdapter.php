<?php

declare(strict_types=1);

namespace OAuth\Infrastructure\OAuth2\League\Repository;

use League\OAuth2\Server\Entities\{ClientEntityInterface, ScopeEntityInterface};
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Repositories\ScopeRepositoryInterface;
use OAuth\Application\Port\Outbound\Client\OAuthClientRepositoryPort;
use OAuth\Domain\ValueObject\Client\OAuthClientIdentifier;
use OAuth\Domain\ValueObject\Scope\Scope;
use OAuth\Domain\ValueObject\Security\GrantType;
use OAuth\Infrastructure\OAuth2\League\Entity\Scope as LeagueScope;

use function is_string;
use function strtoupper;
use function trim;

/**
 * Repository ScopeRepositoryAdapter.
 *
 * @category Repository
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ScopeRepositoryAdapter implements ScopeRepositoryInterface
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param OAuthClientRepositoryPort $clientRepository registered client scope and grant policy
   *
   * @return void
   */
  public function __construct(private OAuthClientRepositoryPort $clientRepository)
  {
  }
  // #endregion

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
    $normalized = is_string($identifier) ? trim($identifier) : '';
    if ('' === $normalized || null === Scope::tryFrom(value: $normalized)) {
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
    $client = $this->clientRepository->find(new OAuthClientIdentifier($clientEntity->getIdentifier()));
    $requestedGrant = GrantType::tryFrom(strtoupper($grantType));
    if (null === $client || !$client->isActive() || null === $requestedGrant || !$client->supportsGrantType($requestedGrant)) {
      throw OAuthServerException::accessDenied('Client is not permitted to use this grant.');
    }
    if ([] === $scopes) {
      throw OAuthServerException::invalidScope('');
    }
    foreach ($scopes as $scope) {
      $requestedScope = Scope::tryFrom($scope->getIdentifier());
      if (null === $requestedScope || !$client->hasScope($requestedScope)) {
        throw OAuthServerException::invalidScope($scope->getIdentifier());
      }
    }

    return $scopes;
  }
  // #endregion
}
