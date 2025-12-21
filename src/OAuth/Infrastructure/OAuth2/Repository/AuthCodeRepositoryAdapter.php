<?php

declare(strict_types=1);

namespace OAuth\Infrastructure\OAuth2\Repository;

use DateTimeImmutable;
use League\OAuth2\Server\Entities\AuthCodeEntityInterface;
use League\OAuth2\Server\Repositories\AuthCodeRepositoryInterface;
use OAuth\Application\Port\Outbound\AuthCodeRepositoryPort;
use OAuth\Domain\Model\AuthCode;
use OAuth\Domain\ValueObject\OAuthClientIdentifier;
use OAuth\Domain\ValueObject\Scopes;
use OAuth\Infrastructure\OAuth2\Entity\AuthCode as LeagueAuthCode;

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
  public function __construct(
    private AuthCodeRepositoryPort $authCodeRepository,
  ) {
  }
  // #endregion

  // #region Methods
  public function getNewAuthCode(): AuthCodeEntityInterface
  {
    return new LeagueAuthCode();
  }

  public function persistNewAuthCode(AuthCodeEntityInterface $authCodeEntity): void
  {
    $code = new AuthCode(
      identifier: $authCodeEntity->getIdentifier(),
      expiryDateTime: DateTimeImmutable::createFromInterface($authCodeEntity->getExpiryDateTime()),
      clientIdentifier: new OAuthClientIdentifier((string) $authCodeEntity->getClient()->getIdentifier()),
      userIdentifier: (string) $authCodeEntity->getUserIdentifier(),
      scopes: Scopes::fromArray(array_map(fn ($scope) => $scope->getIdentifier(), $authCodeEntity->getScopes())),
      redirectUri: $authCodeEntity->getRedirectUri(),
    );

    $this->authCodeRepository->save($code);
  }

  /**
   * @param string $codeId
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
   * @param string $codeId
   */
  public function isAuthCodeRevoked($codeId): bool
  {
    $code = $this->authCodeRepository->find($codeId);

    if (!$code) {
      return true;
    }

    return $code->isRevoked();
  }
  // #endregion
}
