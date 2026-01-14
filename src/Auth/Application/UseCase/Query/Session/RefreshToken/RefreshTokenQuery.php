<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Query\Session\RefreshToken;

use Shared\Application\Message\QueryMessage;

/**
 * Query RefreshTokenQuery.
 *
 * @category Query
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RefreshTokenQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * RefreshTokenQuery class.
   *
   * @since 1.0.0
   *
   * @param string $refreshToken the encrypted refresh token
   * @param string|null $ipAddress the client IP address
   */
  public function __construct(
    public string $refreshToken,
    public ?string $ipAddress = null,
  ) {
  }
  // #endregion
}
