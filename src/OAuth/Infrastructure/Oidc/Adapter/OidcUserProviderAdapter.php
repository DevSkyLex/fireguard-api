<?php

declare(strict_types=1);

namespace OAuth\Infrastructure\Oidc\Adapter;

use OAuth\Application\Port\Outbound\Internal\User\OidcUserProviderPort;
use OAuth\Domain\Model\Oidc\OidcUser;
use Throwable;
use User\Application\Port\Inbound\UserDirectoryPort;

use function trim;

/**
 * Adapter OidcUserProviderAdapter.
 *
 * @category Adapter
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
   * @param UserDirectoryPort $queryBus the query bus
   */
  public function __construct(
    private UserDirectoryPort $queryBus,
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
      $user = $this->queryBus->findById($normalized);
    } catch (Throwable) {
      return null;
    }

    $oidcUser = null;
    if (null !== $user) {
      $oidcUser = new OidcUser(
        subject: $user->id,
        preferredUsername: $user->username,
        email: $user->email,
        emailVerified: $user->emailVerified,
        givenName: $user->firstName,
        familyName: $user->lastName,
        pictureUrl: $user->avatarUrl,
        authTime: $user->lastLoginAt,
      );
    }

    return $oidcUser;
  }
  // #endregion
}
