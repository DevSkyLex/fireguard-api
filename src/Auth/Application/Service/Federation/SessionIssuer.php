<?php

declare(strict_types=1);

namespace Auth\Application\Service\Federation;

use Auth\Application\Port\Outbound\{JwtTokenServicePort, SessionTrackingPort, TrustedDeviceCheckPort};
use Auth\Application\Port\Outbound\Mfa\{ChallengeGeneratorPort, TotpEnrollmentCheckPort};
use Auth\Application\UseCase\Command\Mfa\MfaChallenge\MfaChallengeCommand;
use Auth\Application\UseCase\Command\Session\Login\LoginResult;
use Auth\Domain\Event\Session\UserLoggedInEvent;
use Auth\Domain\Event\Token\TokenIssuedEvent;
use Auth\Domain\ValueObject\Scope\DefaultScopes;
use Auth\Domain\ValueObject\Security\SignInGrantType;
use Shared\Application\Port\Outbound\EventDispatcherPort;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Throwable;
use UnexpectedValueException;
use User\Application\Port\Inbound\FederatedUserPort;

use function array_key_exists;
use function is_array;
use function is_string;

/**
 * Service SessionIssuer.
 *
 * Creates Fireguard sessions after any primary authentication method has
 * established a user identity.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class SessionIssuer
{
  public function __construct(
    private JwtTokenServicePort $tokenService,
    private ChallengeGeneratorPort $challengeGenerator,
    private SessionTrackingPort $sessionTracking,
    private EventDispatcherPort $eventDispatcher,
    private TrustedDeviceCheckPort $trustedDeviceCheck,
    private TotpEnrollmentCheckPort $totpEnrollmentCheck,
    private FederatedUserPort $users,
    #[Autowire('%env(bool:MFA_ENABLED)%')]
    private bool $mfaEnabled,
  ) {
  }

  public function issue(
    string $userId,
    string $email,
    ?string $ipAddress,
    ?string $userAgent,
    ?string $trustedDeviceToken,
    bool $rememberMe,
    SignInGrantType $grantType,
  ): LoginResult {
    if ('' === $userId) {
      throw new UnexpectedValueException('A session requires a user identifier.');
    }
    /** @var non-empty-string $userId */
    $scopes = DefaultScopes::USER_SCOPES;
    if ($this->shouldRequireMfa($userId, $trustedDeviceToken)) {
      $channel = $this->hasActiveTotpEnrollment($userId) ? 'totp' : 'email';
      $challengeCommand = new MfaChallengeCommand(
        userId: $userId,
        purpose: 'login',
        channel: $channel,
        recipient: $email,
      );
      $challenge = $this->challengeGenerator->generate($challengeCommand);
      $preAuthToken = $this->tokenService->generatePreAuthToken(
        userId: $userId,
        challengeToken: $challenge->challengeToken,
        email: $email,
        scopes: $scopes,
        rememberMe: $rememberMe,
        grantType: $grantType,
      );

      return new LoginResult(
        authenticated: true,
        userId: $userId,
        email: $email,
        scopes: $scopes,
        mfaRequired: true,
        mfaToken: $preAuthToken,
        challengeToken: $challenge->challengeToken,
        mfaMethod: $challengeCommand->channel,
        mfaDestination: $challenge->maskedRecipient,
      );
    }

    $tokens = $this->tokenService->generateTokens($userId, $email, $scopes, $rememberMe);
    $this->users->recordSignInMethod($userId, $grantType->method());
    $this->eventDispatcher->dispatch(new UserLoggedInEvent($userId, $email, $ipAddress));
    $this->eventDispatcher->dispatch(new TokenIssuedEvent(
      tokenId: $tokens['access_token'],
      grantType: $grantType->value,
      clientId: 'user_session',
      userId: $userId,
      scopes: $scopes,
      expiresIn: $tokens['expires_in'],
    ));
    $this->recordSession($userId, $ipAddress, $userAgent, $rememberMe, $tokens);

    return new LoginResult(
      authenticated: true,
      userId: $userId,
      email: $email,
      accessToken: $tokens['access_token'],
      refreshToken: $tokens['refresh_token'],
      tokenType: $tokens['token_type'],
      expiresIn: $tokens['expires_in'],
      scopes: $scopes,
    );
  }

  /**
   * @param array<string, mixed> $tokens
   */
  private function recordSession(string $userId, ?string $ipAddress, ?string $userAgent, bool $rememberMe, array $tokens): void
  {
    $accessTokenId = $this->tokenIdentifier($tokens, 'access_token_id');
    $refreshTokenId = $this->tokenIdentifier($tokens, 'refresh_token_id');
    $refreshToken = $this->tokenIdentifier($tokens, 'refresh_token');
    if ((null === $accessTokenId || null === $refreshTokenId) && null !== $refreshToken) {
      $payload = $this->tokenService->decodeRefreshToken($refreshToken);
      if (is_array($payload)) {
        $accessTokenId = $this->tokenIdentifier($payload, 'access_token_id');
        $refreshTokenId = $this->tokenIdentifier($payload, 'refresh_token_id');
      }
    }
    if (null === $accessTokenId || null === $refreshTokenId) {
      return;
    }

    try {
      $this->sessionTracking->recordSession(
        userId: $userId,
        ipAddress: $ipAddress ?? '127.0.0.1',
        userAgent: $userAgent ?? 'unknown',
        accessTokenId: $accessTokenId,
        refreshTokenId: $refreshTokenId,
        rememberMe: $rememberMe,
      );
    } catch (Throwable) {
    }
  }

  /**
   * @param array<string, mixed> $tokens
   */
  private function tokenIdentifier(array $tokens, string $key): ?string
  {
    return array_key_exists($key, $tokens) && is_string($tokens[$key]) && '' !== $tokens[$key]
      ? $tokens[$key]
      : null;
  }

  private function shouldRequireMfa(string $userId, ?string $trustedDeviceToken): bool
  {
    if (!$this->mfaEnabled) {
      return false;
    }
    if (null === $trustedDeviceToken || '' === $trustedDeviceToken) {
      return true;
    }

    try {
      return !$this->trustedDeviceCheck->isTrusted($userId, $trustedDeviceToken);
    } catch (Throwable) {
      return true;
    }
  }

  private function hasActiveTotpEnrollment(string $userId): bool
  {
    try {
      return $this->totpEnrollmentCheck->isEnrolled($userId);
    } catch (Throwable) {
      return false;
    }
  }
}
