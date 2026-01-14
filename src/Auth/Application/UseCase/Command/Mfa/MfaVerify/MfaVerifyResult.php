<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Command\Mfa\MfaVerify;

use Shared\Application\Message\ResultMessage;

/**
 * Result MfaVerifyResult.
 *
 * @category Result
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MfaVerifyResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @param bool $success whether verification succeeded
   * @param int $attemptsRemaining remaining verification attempts
   * @param string|null $error error message if failed
   * @param string|null $accessToken the access token (if success)
   * @param string|null $refreshToken the refresh token (if success)
   * @param string $tokenType the token type
   * @param int $expiresIn token expiration in seconds
   * @param list<string> $scopes the granted scopes (if success)
   */
  public function __construct(
    public bool $success,
    public int $attemptsRemaining = 0,
    public ?string $error = null,
    public ?string $accessToken = null,
    public ?string $refreshToken = null,
    public string $tokenType = 'Bearer',
    public int $expiresIn = 0,
    public array $scopes = [],
  ) {
  }
  // #endregion
}
