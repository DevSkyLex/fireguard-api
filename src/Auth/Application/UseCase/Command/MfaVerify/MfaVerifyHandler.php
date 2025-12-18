<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Command\MfaVerify;

use Auth\Application\Port\Outbound\JwtTokenServicePort;
use Auth\Application\Port\Outbound\Mfa\ChallengeVerifierPort;
use Auth\Domain\Exception\AuthorizationException;
use Shared\Application\Message\CommandHandler;

/**
 * Handler MfaVerifyHandler
 * @final
 *
 * Handles MFA verification and issues final tokens.
 *
 * @category Handler
 * @package Auth\Application\UseCase\Command\MfaVerify
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MfaVerifyHandler implements CommandHandler
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the
   * MfaVerifyHandler class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param JwtTokenServicePort $jwtService The JWT service.
   * @param ChallengeVerifierPort $challengeVerifier The challenge verifier.
   */
  public function __construct(
    private readonly JwtTokenServicePort $jwtService,
    private readonly ChallengeVerifierPort $challengeVerifier,
  ) {
  }
  //#endregion

  //#region Methods
  /**
   * Method __invoke
   *
   * Handles MFA verification command.
   *
   * @access public
   * @since 1.0.0
   *
   * @param MfaVerifyCommand $command The command.
   *
   * @return MfaVerifyResult The result.
   *
   * @throws AuthorizationException If verification fails.
   */
  public function __invoke(MfaVerifyCommand $command): MfaVerifyResult
  {
    // 1. Decode and validate pre-auth token
    $tokenClaims = $this->jwtService->decodePreAuthToken($command->preAuthToken);

    if ($tokenClaims === null) {
      throw AuthorizationException::invalidGrant('Invalid or expired pre-auth token');
    }

    $challengeToken = $tokenClaims['challenge_token'] ?? null;
    $userId = $tokenClaims['sub'] ?? null;
    $email = $tokenClaims['email'] ?? null;

    if (!is_string($challengeToken) || !is_string($userId) || $userId === '') {
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
  //#endregion
}
