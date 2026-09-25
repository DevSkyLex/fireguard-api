<?php

declare(strict_types=1);

namespace OAuth\Application\UseCase\Command\Token\IssueToken;

use OAuth\Application\Port\Outbound\Token\{AccessTokenRepositoryPort, AuthCodeRepositoryPort, AuthorizationServerPort, IdTokenIssuerPort, RefreshTokenRepositoryPort};
use OAuth\Application\Port\Outbound\User\OidcUserProviderPort;
use OAuth\Application\Service\OidcClaimsBuilderInterface;
use OAuth\Domain\Event\Token\{TokenIssueFailedEvent, TokenIssuedEvent};
use OAuth\Domain\Exception\Token\AuthorizationException;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\EventDispatcherPort;
use Throwable;

use function array_filter;
use function array_map;
use function array_values;
use function explode;
use function in_array;
use function is_string;
use function strtolower;
use function trim;

/**
 * Handler IssueTokenHandler.
 *
 * @category Handler
 *
 * @version 2.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class IssueTokenHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * IssueTokenHandler class.
   *
   * @since 1.0.0
   *
   * @param AuthorizationServerPort $authorizationServer the authorization server port
   * @param EventDispatcherPort $eventDispatcher the event dispatcher
   * @param AuthCodeRepositoryPort $authCodeRepository the auth code repository
   * @param IdTokenIssuerPort $idTokenIssuer the ID token issuer
   * @param OidcUserProviderPort $oidcUserProvider the OIDC user provider
   * @param OidcClaimsBuilderInterface $claimsBuilder the OIDC claims builder
   * @param RefreshTokenRepositoryPort $refreshTokenRepository the refresh token repository
   * @param AccessTokenRepositoryPort $accessTokenRepository the access token repository
   */
  public function __construct(
    private readonly AuthorizationServerPort $authorizationServer,
    private readonly EventDispatcherPort $eventDispatcher,
    private readonly AuthCodeRepositoryPort $authCodeRepository,
    private readonly IdTokenIssuerPort $idTokenIssuer,
    private readonly OidcUserProviderPort $oidcUserProvider,
    private readonly OidcClaimsBuilderInterface $claimsBuilder,
    private readonly RefreshTokenRepositoryPort $refreshTokenRepository,
    private readonly AccessTokenRepositoryPort $accessTokenRepository,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke
   * {@inheritdoc}
   *
   * Handles the IssueTokenCommand.
   *
   * @since 1.0.0
   *
   * @param IssueTokenCommand $command the command
   *
   * @return IssueTokenResult the result
   */
  public function __invoke(IssueTokenCommand $command): IssueTokenResult
  {
    try {
      $result = $this->authorizationServer->issueAccessToken(
        grantType: $command->grantType,
        clientId: $command->clientId,
        clientSecret: $command->clientSecret,
        scope: $command->scope,
        refreshToken: $command->refreshToken,
        code: $command->code,
        redirectUri: $command->redirectUri,
        codeVerifier: $command->codeVerifier,
      );

      $context = $this->resolveTokenContext($command, $this->parseScopes($result->scope));
      $scopes = $context['scopes'];
      $normalizedUserId = (null !== $context['userIdentifier'] && '' !== $context['userIdentifier'])
        ? $context['userIdentifier'] : null;

      $idToken = null;
      if ($this->shouldIssueIdToken($command->grantType, $scopes, $normalizedUserId)) {
        /** @var non-empty-string $normalizedUserId */
        $audience = $context['audience'];
        /** @var non-empty-string $audience */
        $claims = $this->buildIdTokenClaims($normalizedUserId, $scopes);
        $idToken = $this->idTokenIssuer->issueIdToken(
          subject: $normalizedUserId,
          audience: $audience,
          nonce: $context['nonce'],
          claims: $claims,
        );
      }

      $this->eventDispatcher->dispatch(event: new TokenIssuedEvent(
        tokenId: $result->accessToken,
        grantType: $command->grantType,
        clientId: $command->clientId,
        userId: $normalizedUserId,
        scopes: $scopes,
        expiresIn: $result->expiresIn,
        ipAddress: $command->ipAddress,
      ));

      return new IssueTokenResult(
        accessToken: $result->accessToken,
        tokenType: $result->tokenType,
        expiresIn: $result->expiresIn,
        refreshToken: $result->refreshToken,
        scope: $result->scope,
        idToken: $idToken,
      );
    } catch (Throwable $exception) {
      $this->eventDispatcher->dispatch(new TokenIssueFailedEvent(
        grantType: $command->grantType,
        clientId: $command->clientId,
        ipAddress: $command->ipAddress,
        reason: $exception->getMessage(),
      ));

      throw $exception;
    }
  }

  /**
   * @param list<string> $scopes
   *
   * @return array{scopes: list<string>, userIdentifier: ?string, audience: string, nonce: ?string}
   */
  private function resolveTokenContext(IssueTokenCommand $command, array $scopes): array
  {
    if ('authorization_code' === $command->grantType && null !== $command->code) {
      return $this->authorizationCodeContext($command->code, $command->clientId, $scopes);
    }
    if ('refresh_token' === $command->grantType && null !== $command->refreshToken) {
      return $this->refreshTokenContext($command->refreshToken, $command->clientId, $scopes);
    }

    return ['scopes' => $scopes, 'userIdentifier' => null, 'audience' => $command->clientId, 'nonce' => null];
  }

  /**
   * @param list<string> $scopes
   *
   * @return array{scopes: list<string>, userIdentifier: ?string, audience: string, nonce: ?string}
   */
  private function authorizationCodeContext(string $code, string $clientId, array $scopes): array
  {
    $authCode = $this->authCodeRepository->findByEncryptedCode($code);
    if (null === $authCode) {
      $authCode = $this->authCodeRepository->find($code);
    }
    if (null === $authCode) {
      return ['scopes' => $scopes, 'userIdentifier' => null, 'audience' => $clientId, 'nonce' => null];
    }

    return [
      'scopes' => [] === $scopes ? $authCode->scopes()->toArray() : $scopes,
      'userIdentifier' => $authCode->userIdentifier(),
      'audience' => (string) $authCode->clientIdentifier(),
      'nonce' => $authCode->nonce(),
    ];
  }

  /**
   * @param list<string> $scopes
   *
   * @return array{scopes: list<string>, userIdentifier: ?string, audience: string, nonce: ?string}
   */
  private function refreshTokenContext(string $token, string $clientId, array $scopes): array
  {
    $refreshToken = $this->refreshTokenRepository->findByEncryptedToken($token);
    if (null === $refreshToken) {
      $refreshToken = $this->refreshTokenRepository->find($token);
    }
    if (null === $refreshToken) {
      return ['scopes' => $scopes, 'userIdentifier' => null, 'audience' => $clientId, 'nonce' => null];
    }

    $audience = (string) $refreshToken->clientIdentifier();
    $accessToken = $this->accessTokenRepository->find($refreshToken->accessTokenIdentifier());
    if (null === $accessToken) {
      return ['scopes' => $scopes, 'userIdentifier' => null, 'audience' => $audience, 'nonce' => null];
    }

    return [
      'scopes' => [] === $scopes ? $accessToken->scopes()->toArray() : $scopes,
      'userIdentifier' => $accessToken->userIdentifier(),
      'audience' => $audience,
      'nonce' => null,
    ];
  }

  /**
   * @return list<string>
   */
  private function parseScopes(?string $scopeValue): array
  {
    if (!is_string($scopeValue) || '' === trim($scopeValue)) {
      return [];
    }

    return array_values(array_filter(explode(' ', $scopeValue), static fn (string $value): bool => '' !== $value));
  }

  /**
   * @param list<string> $scopes
   */
  private function hasOpenIdScope(array $scopes): bool
  {
    $normalized = array_map(static fn (string $scope): string => strtolower($scope), $scopes);

    return in_array('openid', $normalized, true);
  }

  /**
   * @param list<string> $scopes
   */
  private function shouldIssueIdToken(string $grantType, array $scopes, ?string $userIdentifier): bool
  {
    if (null === $userIdentifier || '' === $userIdentifier) {
      return false;
    }

    if (!$this->hasOpenIdScope($scopes)) {
      return false;
    }

    return in_array($grantType, ['authorization_code', 'refresh_token'], true);
  }

  /**
   * @param non-empty-string $userIdentifier
   * @param list<string> $scopes
   *
   * @return array<string, mixed>
   */
  private function buildIdTokenClaims(string $userIdentifier, array $scopes): array
  {
    $oidcUser = $this->oidcUserProvider->findByIdentifier($userIdentifier);

    if (null === $oidcUser) {
      throw AuthorizationException::serverError(
        message: 'User not found for OpenID Connect token.',
        previous: null,
      );
    }

    return $this->claimsBuilder->buildIdTokenClaims(
      user: $oidcUser,
      scopes: $scopes,
    );
  }

  // #endregion
}
