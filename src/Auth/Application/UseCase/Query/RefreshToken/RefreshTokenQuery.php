<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Query\RefreshToken;

use Shared\Application\Message\QueryMessage;

/**
 * Query RefreshTokenQuery
 * @final
 *
 * Query to refresh an access token using a refresh token.
 *
 * @category Query
 * @package Auth\Application\UseCase\Query\RefreshToken
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RefreshTokenQuery implements QueryMessage
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the
   * RefreshTokenQuery class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $refreshToken The encrypted refresh token.
   */
  public function __construct(
    public string $refreshToken,
    public ?string $ipAddress = null,
  ) {}
  //#endregion
}
