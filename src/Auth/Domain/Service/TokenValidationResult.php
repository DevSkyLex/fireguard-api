<?php

declare(strict_types=1);

namespace Auth\Domain\Service;

/**
 * Class TokenValidationResult
 * @final
 *
 * Result of token validation.
 *
 * @category Service
 * @package Auth\Domain\Service
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class TokenValidationResult
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the
   * TokenValidationResult class.
   *
   * @access private
   * @since 1.0.0
   *
   * @param bool $valid Whether the token is valid.
   * @param string|null $errorCode The error code if invalid.
   * @param string|null $errorMessage The error message if invalid.
   * @param string|null $tokenId The token identifier.
   * @param string|null $userId The user identifier.
   * @param string|null $clientId The client identifier.
   * @param list<string> $scopes The token scopes.
   * @param int|null $expiresAt The expiration timestamp.
   */
  private function __construct(
    public bool $valid,
    public ?string $errorCode = null,
    public ?string $errorMessage = null,
    public ?string $tokenId = null,
    public ?string $userId = null,
    public ?string $clientId = null,
    public array $scopes = [],
    public ?int $expiresAt = null,
  ) {}
  //#endregion

  //#region Factory Methods
  /**
   * Method success
   *
   * Creates a successful validation result.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string|null $tokenId The token identifier.
   * @param string|null $userId The user identifier.
   * @param string|null $clientId The client identifier.
   * @param list<string> $scopes The token scopes.
   * @param int|null $expiresAt The expiration timestamp.
   *
   * @return self The result.
   */
  public static function success(
    ?string $tokenId = null,
    ?string $userId = null,
    ?string $clientId = null,
    array $scopes = [],
    ?int $expiresAt = null,
  ): self {
    return new self(
      valid: true,
      tokenId: $tokenId,
      userId: $userId,
      clientId: $clientId,
      scopes: $scopes,
      expiresAt: $expiresAt,
    );
  }

  /**
   * Method failed
   *
   * Creates a failed validation result.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $errorCode The error code.
   * @param string $errorMessage The error message.
   *
   * @return self The result.
   */
  public static function failed(string $errorCode, string $errorMessage): self
  {
    return new self(
      valid: false,
      errorCode: $errorCode,
      errorMessage: $errorMessage,
    );
  }
  //#endregion

  //#region Methods
  /**
   * Method isExpired
   *
   * Checks if the error is due to expiration.
   *
   * @access public
   * @since 1.0.0
   *
   * @return bool True if expired.
   */
  public function isExpired(): bool
  {
    return $this->errorCode === TokenValidationService::VALIDATION_EXPIRED;
  }

  /**
   * Method isRevoked
   *
   * Checks if the error is due to revocation.
   *
   * @access public
   * @since 1.0.0
   *
   * @return bool True if revoked.
   */
  public function isRevoked(): bool
  {
    return $this->errorCode === TokenValidationService::VALIDATION_REVOKED;
  }
  //#endregion
}
