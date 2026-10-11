<?php

declare(strict_types=1);

namespace OAuth\Application\UseCase\Query\User\GetUserInfo;

use OAuth\Application\Port\Outbound\Internal\User\{OidcClaimsBuilderPort, OidcUserProviderPort};
use Shared\Application\Message\QueryHandler;
use Throwable;

use function array_map;
use function in_array;
use function strtolower;

/**
 * Applies scope and account availability before building OIDC claims.
 *
 * @category Handler
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetUserInfoHandler implements QueryHandler
{
  public function __construct(private OidcUserProviderPort $users, private OidcClaimsBuilderPort $claims)
  {
  }

  public function __invoke(GetUserInfoQuery $query): GetUserInfoResult
  {
    if (!in_array('openid', array_map(strtolower(...), $query->scopes), true)) {
      return new GetUserInfoResult(null, 'Token does not have openid scope');
    }

    try {
      $user = $this->users->findByIdentifier($query->userId);

      return null === $user
        ? new GetUserInfoResult(null, 'User not found')
        : new GetUserInfoResult($this->claims->buildUserInfoClaims($user, $query->scopes));
    } catch (Throwable $exception) {
      return new GetUserInfoResult(null, 'Failed to get user info: ' . $exception->getMessage());
    }
  }
}
