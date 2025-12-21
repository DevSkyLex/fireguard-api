<?php

declare(strict_types=1);

namespace OAuth\Application\UseCase\Command\IssueToken;

use Shared\Application\Message\ResultMessage;

/**
 * Result IssueTokenResult.
 *
 * @category Result
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class IssueTokenResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * IssueTokenResult class.
   *
   * @since 1.0.0
   *
   * @param string      $accessToken  the access token
   * @param string      $tokenType    the token type (Bearer)
   * @param int         $expiresIn    the expiration time in seconds
   * @param string|null $refreshToken the refresh token (optional)
   * @param string|null $scope        the granted scope (optional)
   */
  public function __construct(
    public readonly string $accessToken,
    public readonly string $tokenType,
    public readonly int $expiresIn,
    public readonly ?string $refreshToken = null,
    public readonly ?string $scope = null,
  ) {
  }
  // #endregion
}
