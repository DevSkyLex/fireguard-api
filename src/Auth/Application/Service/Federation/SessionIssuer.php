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
 * Class SessionIssuer
 *
 * Creates Fireguard sessions after any primary authentication method has
 * established a user identity.
 *
 * @category Service
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class SessionIssuer
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Supplies token, MFA, session-tracking, user and event ports used to establish an authenticated session.
   *
   * @access public
   *
   * @param JwtTokenServicePort $tokenService issues session and pre-authentication tokens
   * @param ChallengeGeneratorPort $challengeGenerator creates the MFA challenge when required
   * @param SessionTrackingPort $sessionTracking records the issued token pair against a session
   * @param EventDispatcherPort $eventDispatcher publishes login and token-issuance events
   * @param TrustedDeviceCheckPort $trustedDeviceCheck checks whether MFA can be skipped for a trusted device
   * @param TotpEnrollmentCheckPort $totpEnrollmentCheck selects TOTP when the user has an active enrollment
   * @param FederatedUserPort $users records the successful sign-in method
   * @param bool $mfaEnabled whether interactive sign-in requires MFA checks
   *
   * @return void
   */
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
  // #endregion

  // #region Methods
  /**
   * Method issue
   *
   * Starts MFA when policy requires it; otherwise issues tokens, records their session anchor, then dispatches login events.
   *
   * @access public
   *
   * @param string $userId the authenticated user's identifier
   * @param string $email the authenticated user's email address
   * @param ?string $ipAddress the request address recorded with the session
   * @param ?string $userAgent the request agent recorded with the session
   * @param ?string $trustedDeviceToken the token presented to test a trusted device
   * @param bool $rememberMe whether the session uses the remembered duration
   * @param SignInGrantType $grantType the authentication method that established the identity
   *
   * @return LoginResult the MFA challenge or issued session credentials
   *
   * @throws UnexpectedValueException when the user identifier is empty or token identifiers are unavailable
   */
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
    $accessTokenId = $this->recordSession($userId, $ipAddress, $userAgent, $rememberMe, $tokens);
    $this->eventDispatcher->dispatch(new UserLoggedInEvent($userId, $email, $ipAddress));
    $this->eventDispatcher->dispatch(new TokenIssuedEvent(
      tokenId: $accessTokenId,
      grantType: $grantType->value,
      clientId: 'user_session',
      userId: $userId,
      scopes: $scopes,
      expiresIn: $tokens['expires_in'],
    ));

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
   * Method recordSession
   *
   * Records the issued token pair as the session anchor after resolving identifiers from token metadata or the refresh token.
   *
   * @access private
   *
   * @param string $userId authenticated user identifier
   * @param ?string $ipAddress request address stored with the session
   * @param ?string $userAgent request agent stored with the session
   * @param bool $rememberMe whether the session uses the remembered duration
   * @param array<string, mixed> $tokens generated token data containing token identifiers
   *
   * @return string access-token identifier used to bind later authentication to this session
   *
   * @throws UnexpectedValueException when either token identifier cannot be resolved
   */
  private function recordSession(string $userId, ?string $ipAddress, ?string $userAgent, bool $rememberMe, array $tokens): string
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
      throw new UnexpectedValueException('Session token identifiers are required.');
    }

    $this->sessionTracking->recordSession(
      userId: $userId,
      ipAddress: $ipAddress ?? '127.0.0.1',
      userAgent: $userAgent ?? 'unknown',
      accessTokenId: $accessTokenId,
      refreshTokenId: $refreshTokenId,
      rememberMe: $rememberMe,
    );

    return $accessTokenId;
  }

  /**
   * Method tokenIdentifier
   *
   * Returns a non-empty string field from token metadata, ignoring absent or invalid values.
   *
   * @access private
   *
   * @param array<string, mixed> $tokens token metadata to inspect
   * @param string $key token field name
   *
   * @return ?string non-empty token value, or null
   */
  private function tokenIdentifier(array $tokens, string $key): ?string
  {
    return array_key_exists($key, $tokens) && is_string($tokens[$key]) && '' !== $tokens[$key]
      ? $tokens[$key]
      : null;
  }

  /**
   * Method shouldRequireMfa
   *
   * Requires MFA when enabled unless the supplied device token is verified; check failures fail closed.
   *
   * @access private
   *
   * @param string $userId the user whose device trust is checked
   * @param ?string $trustedDeviceToken the presented trusted-device token, if any
   *
   * @return bool whether an MFA challenge must be issued
   */
  private function shouldRequireMfa(string $userId, ?string $trustedDeviceToken): bool
  {
    if (!$this->mfaEnabled) {
      return false;
    }
    if (null === $trustedDeviceToken || '' === $trustedDeviceToken) {
      return true;
    }

    try {
      $isTrusted = $this->trustedDeviceCheck->isTrusted($userId, $trustedDeviceToken);
    } catch (Throwable) {
      $isTrusted = false;
    }

    return !$isTrusted;
  }

  /**
   * Method hasActiveTotpEnrollment
   *
   * Checks whether TOTP is enrolled so login MFA can choose it instead of email; lookup failures select email.
   *
   * @access private
   *
   * @param string $userId the user whose enrollment is checked
   *
   * @return bool whether the user has an active TOTP enrollment
   */
  private function hasActiveTotpEnrollment(string $userId): bool
  {
    try {
      return $this->totpEnrollmentCheck->isEnrolled($userId);
    } catch (Throwable) {
      return false;
    }
  }
  // #endregion
}
