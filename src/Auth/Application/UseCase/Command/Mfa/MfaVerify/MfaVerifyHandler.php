<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Command\Mfa\MfaVerify;

use Auth\Application\Port\Outbound\{JwtTokenServicePort, SessionTrackingPort};
use Auth\Application\Port\Outbound\Mfa\ChallengeVerifierPort;
use Auth\Domain\Exception\Session\AuthorizationException;
use Auth\Domain\ValueObject\Scope\DefaultScopes;
use Shared\Application\Message\CommandHandler;
use Throwable;

use function array_filter;
use function array_values;
use function is_array;
use function is_string;

/**
 * Handler MfaVerifyHandler.
 *
 * @category Handler
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MfaVerifyHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * MfaVerifyHandler class.
   *
   * @since 1.0.0
   *
   * @param JwtTokenServicePort $jwtService the JWT service
   * @param ChallengeVerifierPort $challengeVerifier the challenge verifier
   * @param SessionTrackingPort $sessionTracking the session tracking port
   */
  public function __construct(
    private readonly JwtTokenServicePort $jwtService,
    private readonly ChallengeVerifierPort $challengeVerifier,
    private readonly SessionTrackingPort $sessionTracking,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Handles MFA verification command.
   *
   * @since 1.0.0
   *
   * @param MfaVerifyCommand $command the command
   *
   * @throws AuthorizationException if verification fails
   *
   * @return MfaVerifyResult the result
   */
  public function __invoke(MfaVerifyCommand $command): MfaVerifyResult
  {
    // 1. Decode and validate pre-auth token
    $tokenClaims = $this->jwtService->decodePreAuthToken($command->preAuthToken);

    if (null === $tokenClaims) {
      throw AuthorizationException::invalidGrant('Invalid or expired pre-auth token');
    }

    $challengeToken = $tokenClaims['challenge_token'] ?? null;
    $userId = $tokenClaims['sub'] ?? null;
    $email = $tokenClaims['email'] ?? null;
    $scopesClaim = $tokenClaims['scopes'] ?? DefaultScopes::USER_SCOPES;

    if (!is_string($challengeToken) || !is_string($userId) || '' === $userId) {
      throw AuthorizationException::invalidGrant('Invalid pre-auth token payload');
    }

    $scopes = DefaultScopes::USER_SCOPES;
    if (is_array($scopesClaim)) {
      $scopes = array_values(array_filter($scopesClaim, 'is_string'));
      if ([] === $scopes) {
        $scopes = DefaultScopes::USER_SCOPES;
      }
    }

    // 2. Verify OTP via port
    $verificationResult = $this->challengeVerifier->verify($challengeToken, $command->code);

    if (!$verificationResult->success) {
      return new MfaVerifyResult(
        success: false,
        attemptsRemaining: $verificationResult->attemptsRemaining,
        error: $verificationResult->error,
      );
    }

    // 3. Generate final tokens
    $tokens = $this->jwtService->generateTokens(
      userId: $userId,
      email: is_string($email) ? $email : '',
      scopes: $scopes,
    );

    $this->recordSession($command, $userId, $tokens);

    return new MfaVerifyResult(
      success: true,
      attemptsRemaining: $verificationResult->attemptsRemaining,
      accessToken: $tokens['access_token'],
      refreshToken: $tokens['refresh_token'],
      tokenType: $tokens['token_type'],
      expiresIn: $tokens['expires_in'],
      scopes: $scopes,
    );
  }

  /**
   * Method recordSession.
   *
   * Tracks a user session after MFA success.
   *
   * @param MfaVerifyCommand $command the MFA command
   * @param string $userId the user ID
   * @param array{access_token: string, refresh_token: string} $tokens issued tokens
   *
   * @return void no return value
   */
  private function recordSession(MfaVerifyCommand $command, string $userId, array $tokens): void
  {
    $refreshPayload = $this->jwtService->decodeRefreshToken($tokens['refresh_token']);
    $accessTokenId = null;
    $refreshTokenId = null;
    if (is_array($refreshPayload)) {
      $accessTokenId = $refreshPayload['access_token_id'];
      $refreshTokenId = $refreshPayload['refresh_token_id'];
    }

    $ipAddress = $command->ipAddress ?? '127.0.0.1';
    $userAgent = $command->userAgent ?? 'unknown';

    try {
      $this->sessionTracking->recordSession(
        userId: $userId,
        ipAddress: $ipAddress,
        userAgent: $userAgent,
        accessTokenId: $accessTokenId,
        refreshTokenId: $refreshTokenId,
        rememberMe: $command->rememberMe,
      );
    } catch (Throwable) {
      // Best-effort session tracking; MFA verification must not fail.
    }
  }
  // #endregion
}
