<?php

declare(strict_types=1);

namespace OAuth\Application\UseCase\Query\ValidateToken;

use Shared\Application\Message\ResultMessage;

/**
 * Result ValidateTokenResult
 * @final
 *
 * Result of the ValidateTokenQuery.
 *
 * @category Result
 * @package OAuth\Application\UseCase\Query\ValidateToken
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ValidateTokenResult implements ResultMessage
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the
   * ValidateTokenResult class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param bool $valid Whether the token is valid.
   * @param string|null $tokenId The token identifier.
   * @param string|null $userId The user identifier.
   * @param string|null $clientId The client identifier.
   * @param list<string> $scopes The token scopes.
   * @param int|null $expiresAt The expiration timestamp.
   * @param string|null $errorMessage Error message if invalid.
   */
  public function __construct(
    public bool $valid,
    public ?string $tokenId = null,
    public ?string $userId = null,
    public ?string $clientId = null,
    public array $scopes = [],
    public ?int $expiresAt = null,
    public ?string $errorMessage = null,
  ) {}
  //#endregion

  //#region Methods
  /**
   * Method invalid
   *
   * Creates an invalid result.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $message The error message.
   *
   * @return self The invalid result.
   */
  public static function invalid(string $message = 'Invalid token'): self
  {
    return new self(
      valid: false,
      errorMessage: $message,
    );
  }
  //#endregion
}
