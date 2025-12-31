<?php

declare(strict_types=1);

namespace OAuth\Infrastructure\OAuth2\League\Repository;

use DateTimeImmutable;
use League\OAuth2\Server\Entities\AuthCodeEntityInterface;
use League\OAuth2\Server\Repositories\AuthCodeRepositoryInterface;
use OAuth\Application\Port\Outbound\Token\AuthCodeRepositoryPort;
use OAuth\Domain\Model\Token\AuthCode;
use OAuth\Domain\ValueObject\Client\OAuthClientIdentifier;
use OAuth\Domain\ValueObject\Scope\Scopes;
use OAuth\Infrastructure\OAuth2\League\Entity\AuthCode as LeagueAuthCode;

use function array_map;

/**
 * Repository AuthCodeRepositoryAdapter.
 *
 * @category Repository
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AuthCodeRepositoryAdapter implements AuthCodeRepositoryInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initialize the AuthCodeRepositoryAdapter.
   *
   * @since 1.0.0
   *
   * @param AuthCodeRepositoryPort $authCodeRepository the domain auth code repository
   */
  public function __construct(
    private AuthCodeRepositoryPort $authCodeRepository,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method getNewAuthCode
   * {@inheritDoc}
   *
   * Get new auth code entity.
   *
   * @since 1.0.0
   *
   * @return AuthCodeEntityInterface the auth code entity
   */
  public function getNewAuthCode(): AuthCodeEntityInterface
  {
    return new LeagueAuthCode();
  }

  /**
   * Method persistNewAuthCode
   * {@inheritDoc}
   *
   * Persist new auth code.
   *
   * @since 1.0.0
   *
   * @param AuthCodeEntityInterface $authCodeEntity the auth code entity
   *
   * @return void No return value
   */
  public function persistNewAuthCode(AuthCodeEntityInterface $authCodeEntity): void
  {
    $code = new AuthCode(
      identifier: $authCodeEntity->getIdentifier(),
      expiryDateTime: DateTimeImmutable::createFromInterface($authCodeEntity->getExpiryDateTime()),
      clientIdentifier: new OAuthClientIdentifier((string) $authCodeEntity->getClient()->getIdentifier()),
      userIdentifier: $authCodeEntity->getUserIdentifier(),
      scopes: Scopes::fromArray(array_map(fn ($scope) => $scope->getIdentifier(), $authCodeEntity->getScopes())),
      redirectUri: $authCodeEntity->getRedirectUri(),
      nonce: null,
    );

    $this->authCodeRepository->save($code);
  }

  /**
   * Method revokeAuthCode
   * {@inheritDoc}
   *
   * Revoke an auth code by its ID.
   *
   * @since 1.0.0
   *
   * @param string $codeId the ID of the auth code to revoke
   *
   * @return void No return value
   */
  public function revokeAuthCode(string $codeId): void
  {
    $code = $this->authCodeRepository->find($codeId);

    if ($code) {
      $code->revoke();
      $this->authCodeRepository->save($code);
    }
  }

  /**
   * Method isAuthCodeRevoked
   * {@inheritdoc}
   *
   * Check if the auth code is revoked.
   *
   * @since 1.0.0
   *
   * @param string $codeId the auth code identifier
   *
   * @return bool True if revoked
   */
  public function isAuthCodeRevoked(string $codeId): bool
  {
    $code = $this->authCodeRepository->find($codeId);

    if (!$code) {
      return true;
    }

    return $code->isRevoked();
  }
  // #endregion
}
