<?php

declare(strict_types=1);

namespace OAuth\Application\UseCase\Query\ValidateToken;

use Shared\Application\Message\QueryMessage;

/**
 * Query ValidateTokenQuery
 * @final
 *
 * Query to validate an access token.
 *
 * @category Query
 * @package OAuth\Application\UseCase\Query\ValidateToken
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ValidateTokenQuery implements QueryMessage
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the
   * ValidateTokenQuery class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $accessToken The access token to validate.
   */
  public function __construct(
    public string $accessToken,
  ) {}
  //#endregion
}
