<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Command\RevokeToken;

use Shared\Application\Message\CommandMessage;

/**
 * Command RevokeTokenCommand
 * @final
 *
 * Command to revoke a token (OAuth2 RFC 7009).
 *
 * @category Command
 * @package Auth\Application\UseCase\Command\RevokeToken
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RevokeTokenCommand implements CommandMessage
{
  /**
   * Token type hint constants
   */
  public const string HINT_ACCESS_TOKEN = 'access_token';
  public const string HINT_REFRESH_TOKEN = 'refresh_token';

  /**
   * Constructor
   *
   * @param string $token The token to revoke.
   * @param string|null $tokenTypeHint Optional hint about the token type.
   */
  public function __construct(
    public string $token,
    public ?string $tokenTypeHint = null,
  ) {}
}
