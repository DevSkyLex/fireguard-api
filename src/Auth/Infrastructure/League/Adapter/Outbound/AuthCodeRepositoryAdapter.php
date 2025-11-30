<?php

declare(strict_types=1);

namespace Auth\Infrastructure\League\Adapter\Outbound;

use Auth\Application\Port\Outbound\AuthCodeRepositoryPort;
use Auth\Domain\Model\AuthCode;
use Auth\Infrastructure\League\Model\AuthCode as LeagueAuthCode;
use DateTimeImmutable;
use League\OAuth2\Server\Entities\AuthCodeEntityInterface;
use League\OAuth2\Server\Repositories\AuthCodeRepositoryInterface;
use Shared\Domain\ValueObject\OAuthClientIdentifier;
use Shared\Domain\ValueObject\Scopes;

/**
 * Adapter AuthCodeRepositoryAdapter
 * @final
 *
 * Adapter for League AuthCodeRepositoryInterface.
 *
 * @category Adapter
 * @package Auth\Infrastructure\League\Adapter\Outbound
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AuthCodeRepositoryAdapter implements AuthCodeRepositoryInterface
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the
   * AuthCodeRepositoryAdapter class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param AuthCodeRepositoryPort $authCodeRepository The domain auth code repository.
   */
  public function __construct(
    private AuthCodeRepositoryPort $authCodeRepository
  ) {
  }
  //#endregion

  //#region Methods
  /**
   * Method getNewAuthCode
   * {@inheritDoc}
   *
   * Get a new auth code.
   *
   * @access public
   * @since 1.0.0
   *
   * @return AuthCodeEntityInterface The auth code entity.
   */
  public function getNewAuthCode(): AuthCodeEntityInterface
  {
    return new LeagueAuthCode();
  }

  /**
   * Method persistNewAuthCode
   * {@inheritDoc}
   *
   * Persist a new auth code.
   *
   * @access public
   * @since 1.0.0
   *
   * @param AuthCodeEntityInterface $authCodeEntity The auth code entity.
   *
   * @return void
   */
  public function persistNewAuthCode(AuthCodeEntityInterface $authCodeEntity): void
  {
    $code = new AuthCode(
      identifier: $authCodeEntity->getIdentifier(),
      expiryDateTime: DateTimeImmutable::createFromInterface($authCodeEntity->getExpiryDateTime()),
      clientIdentifier: new OAuthClientIdentifier((string) $authCodeEntity->getClient()->getIdentifier()),
      userIdentifier: (string) $authCodeEntity->getUserIdentifier(),
      scopes: Scopes::fromArray(array_map(fn($scope) => $scope->getIdentifier(), $authCodeEntity->getScopes())),
      redirectUri: $authCodeEntity->getRedirectUri()
    );

    $this->authCodeRepository->save($code);
  }

  /**
   * Method revokeAuthCode
   * {@inheritDoc}
   *
   * Revoke an auth code.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $codeId The auth code identifier.
   *
   * @return void
   */
  public function revokeAuthCode($codeId): void
  {
    $code = $this->authCodeRepository->find($codeId);

    if ($code) {
      $code->revoke();
      $this->authCodeRepository->save($code);
    }
  }

  /**
   * Method isAuthCodeRevoked
   * {@inheritDoc}
   *
   * Check if an auth code is revoked.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $codeId The auth code identifier.
   *
   * @return bool True if revoked, false otherwise.
   */
  public function isAuthCodeRevoked($codeId): bool
  {
    $code = $this->authCodeRepository->find($codeId);

    if (!$code) {
      return true;
    }

    return $code->isRevoked();
  }
  //#endregion
}
