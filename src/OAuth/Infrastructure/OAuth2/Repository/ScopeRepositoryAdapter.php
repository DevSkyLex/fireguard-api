<?php

declare(strict_types=1);

namespace OAuth\Infrastructure\OAuth2\Repository;

use OAuth\Infrastructure\OAuth2\Entity\Scope as LeagueScope;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Repositories\ScopeRepositoryInterface;
use OAuth\Domain\Exception\InvalidScopeException;
use OAuth\Domain\ValueObject\Scope;

/**
 * Repository ScopeRepositoryAdapter
 * @final
 *
 * Adapter implementing League's ScopeRepositoryInterface.
 *
 * @category Repository
 * @package OAuth\Infrastructure\OAuth2\Repository
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ScopeRepositoryAdapter implements ScopeRepositoryInterface
{
  //#region Methods
  /**
   * {@inheritDoc}
   */
  public function getScopeEntityByIdentifier($identifier): ?LeagueScope
  {
    try {
      if (empty($identifier))
        return null;

      $domainScope = Scope::tryFrom(value: $identifier);
      if ($domainScope === null)
        return null;

      $scope = new LeagueScope();
      $scope->setIdentifier(identifier: $identifier);

      return $scope;
    } catch (InvalidScopeException $exception) {
      return null;
    }
  }

  /**
   * {@inheritDoc}
   */
  /**
   * @param string $grantType
   * @param string|int|null $userIdentifier
   * @param string|null $authCodeId
   */
  public function finalizeScopes(
    array $scopes,
    $grantType,
    ClientEntityInterface $clientEntity,
    $userIdentifier = null,
    $authCodeId = null
  ): array {
    return $scopes;
  }
  //#endregion
}
