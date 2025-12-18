<?php

declare(strict_types=1);

namespace OAuth\Application\UseCase\Command\IssueToken;

use Shared\Application\Message\ResultMessage;

/**
 * Result IssueTokenResult
 * @final
 *
 * Result of the IssueTokenCommand.
 *
 * @category Result
 * @package OAuth\Application\UseCase\Command\IssueToken
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class IssueTokenResult implements ResultMessage
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the
   * IssueTokenResult class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $accessToken The access token.
   * @param string $tokenType The token type (Bearer).
   * @param int $expiresIn The expiration time in seconds.
   * @param string|null $refreshToken The refresh token (optional).
   * @param string|null $scope The granted scope (optional).
   */
  public function __construct(
    public readonly string $accessToken,
    public readonly string $tokenType,
    public readonly int $expiresIn,
    public readonly ?string $refreshToken = null,
    public readonly ?string $scope = null
  ) {
  }
  //#endregion
}
