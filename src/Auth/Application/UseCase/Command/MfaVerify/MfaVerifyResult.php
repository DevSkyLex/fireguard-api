<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Command\MfaVerify;

use Shared\Application\Message\ResultMessage;

/**
 * Result MfaVerifyResult
 * @final
 *
 * Result of MFA verification.
 *
 * @category Result
 * @package Auth\Application\UseCase\Command\MfaVerify
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MfaVerifyResult implements ResultMessage
{
  //#region Constructor
  /**
   * Constructor
   *
   * @param bool $success Whether verification succeeded.
   * @param int $attemptsRemaining Remaining verification attempts.
   * @param string|null $error Error message if failed.
   * @param string|null $accessToken The access token (if success).
   * @param string|null $refreshToken The refresh token (if success).
   * @param string $tokenType The token type.
   * @param int $expiresIn Token expiration in seconds.
   */
  public function __construct(
    public bool $success,
    public int $attemptsRemaining = 0,
    public ?string $error = null,
    public ?string $accessToken = null,
    public ?string $refreshToken = null,
    public string $tokenType = 'Bearer',
    public int $expiresIn = 0,
  ) {
  }
  //#endregion
}
