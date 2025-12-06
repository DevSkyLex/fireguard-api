<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Query\IntrospectToken;

use Shared\Application\Message\QueryMessage;

/**
 * Query IntrospectTokenQuery
 * @final
 *
 * Query to introspect a token (RFC 7662).
 *
 * @category Query
 * @package Auth\Application\UseCase\Query\IntrospectToken
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class IntrospectTokenQuery implements QueryMessage
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the
   * IntrospectTokenQuery class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $token The token to introspect.
   * @param string $tokenTypeHint The token type hint (access_token, refresh_token).
   */
  public function __construct(
    public string $token,
    public string $tokenTypeHint = 'access_token',
  ) {}
  //#endregion
}
