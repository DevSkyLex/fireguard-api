<?php

declare(strict_types=1);

namespace OAuth\Application\UseCase\Query\ValidateToken;

use Shared\Application\Message\ResultMessage;

/**
 * Result ValidateTokenResult.
 *
 * @category Result
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ValidateTokenResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * ValidateTokenResult class.
   *
   * @since 1.0.0
   *
   * @param bool $valid whether the token is valid
   * @param string|null $tokenId the token identifier
   * @param string|null $userId the user identifier
   * @param string|null $clientId the client identifier
   * @param list<string> $scopes the token scopes
   * @param int|null $expiresAt the expiration timestamp
   * @param string|null $errorMessage error message if invalid
   */
  public function __construct(
    public bool $valid,
    public ?string $tokenId = null,
    public ?string $userId = null,
    public ?string $clientId = null,
    public array $scopes = [],
    public ?int $expiresAt = null,
    public ?string $errorMessage = null,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method invalid.
   *
   * Creates an invalid result.
   *
   * @since 1.0.0
   *
   * @param string $message the error message
   *
   * @return self the invalid result
   */
  public static function invalid(string $message = 'Invalid token'): self
  {
    return new self(
      valid: false,
      errorMessage: $message,
    );
  }
  // #endregion
}
