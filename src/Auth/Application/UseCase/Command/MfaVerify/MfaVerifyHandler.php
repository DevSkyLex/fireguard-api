<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Command\MfaVerify;

use Auth\Application\Port\Outbound\JwtTokenServicePort;
use Auth\Application\Port\Outbound\Mfa\ChallengeVerifierPort;
use Auth\Domain\Exception\AuthorizationException;
use Shared\Application\Message\CommandHandler;

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
   * @param JwtTokenServicePort   $jwtService        the JWT service
   * @param ChallengeVerifierPort $challengeVerifier the challenge verifier
   */
  public function __construct(
    private readonly JwtTokenServicePort $jwtService,
    private readonly ChallengeVerifierPort $challengeVerifier,
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
   * @return MfaVerifyResult the result
   *
   * @throws AuthorizationException if verification fails
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

    if (!is_string($challengeToken) || !is_string($userId) || '' === $userId) {
      throw AuthorizationException::invalidGrant('Invalid pre-auth token payload');
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
      scopes: [],
    );

    return new MfaVerifyResult(
      success: true,
      attemptsRemaining: $verificationResult->attemptsRemaining,
      accessToken: $tokens['access_token'],
      refreshToken: $tokens['refresh_token'],
      tokenType: $tokens['token_type'],
      expiresIn: $tokens['expires_in'],
    );
  }
  // #endregion
}
