<?php

declare(strict_types=1);

namespace OAuth\Infrastructure\Oidc\Adapter;

use OAuth\Application\Port\Outbound\User\OidcUserProviderPort;
use OAuth\Domain\Model\Oidc\OidcUser;
use Shared\Application\Port\Inbound\QueryBusPort;
use Throwable;
use User\Application\UseCase\Query\GetUser\GetUserQuery;
use User\Application\UseCase\Query\GetUser\GetUserResult;

use function trim;

/**
 * Adapter OidcUserProviderAdapter.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OidcUserProviderAdapter implements OidcUserProviderPort
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * OidcUserProviderAdapter class.
   *
   * @since 1.0.0
   *
   * @param QueryBusPort $queryBus the query bus
   */
  public function __construct(
    private QueryBusPort $queryBus,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method findByIdentifier
   * {@inheritDoc}
   *
   * @return OidcUser|null the OIDC user or null if not found
   */
  public function findByIdentifier(string $identifier): ?OidcUser
  {
    $normalized = trim($identifier);
    if ('' === $normalized) {
      return null;
    }

    try {
      /** @var GetUserResult $result */
      $result = $this->queryBus->ask(new GetUserQuery(id: $normalized));
    } catch (Throwable) {
      return null;
    }

    if (null === $result->user) {
      return null;
    }

    $user = $result->user;
    $profile = $user->profile();

    return new OidcUser(
      subject: $user->id()->value,
      preferredUsername: (string) $user->username(),
      email: (string) $user->email(),
      emailVerified: $user->isEmailVerified(),
      givenName: $profile->firstName,
      familyName: $profile->lastName,
      pictureUrl: $profile->avatarUrl,
      authTime: $user->lastLoginAt(),
    );
  }
  // #endregion
}
