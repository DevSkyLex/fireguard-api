<?php

declare(strict_types=1);

namespace Auth\Infrastructure\League\Adapter\Outbound;

use Auth\Infrastructure\League\Model\Scope as LeagueScope;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Repositories\ScopeRepositoryInterface;
use Shared\Domain\Exception\InvalidScopeException;
use Shared\Domain\ValueObject\Scope;

/**
 * Adapter ScopeRepositoryAdapter
 * @final
 *
 * Adapter for League ScopeRepositoryInterface.
 *
 * @category Adapter
 * @package Auth\Infrastructure\League\Adapter\Outbound
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ScopeRepositoryAdapter implements ScopeRepositoryInterface
{
  //#region Methods
  /**
   * Method getScopeEntityByIdentifier
   * {@inheritDoc}
   *
   * Get a scope entity by identifier.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $identifier The scope identifier.
   *
   * @return LeagueScope|null The scope entity or null if not found.
   */
  public function getScopeEntityByIdentifier($identifier): ?LeagueScope
  {
    try {
      if (empty($identifier)) return null;

      // Validate scope format using Domain Value Object
      $domainScope = Scope::tryFrom(value: $identifier);
      if ($domainScope === null) return null;

      $scope = new LeagueScope();
      $scope->setIdentifier(identifier: $identifier);

      return $scope;
    }
    catch (InvalidScopeException $exception) {
      return null;
    }
  }

  /**
   * Method finalizeScopes
   * {@inheritDoc}
   *
   * Finalize the scopes.
   *
   * @access public
   * @since 1.0.0
   *
   * @param array<mixed> $scopes The scopes.
   * @param string $grantType The grant type.
   * @param ClientEntityInterface $clientEntity The client entity.
   * @param string|null $userIdentifier The user identifier.
   * @param string|null $authCodeId The authorization code identifier.
   *
   * @return array<mixed> The final scopes.
   */
  public function finalizeScopes(
    array $scopes,
    $grantType,
    ClientEntityInterface $clientEntity,
    $userIdentifier = null,
    $authCodeId = null
  ): array {
    // Here we could filter scopes based on client permissions.
    // For now, we accept all requested scopes that are valid.
    // If no scopes requested, we could return default scopes.

    return $scopes;
  }
  //#endregion
}
