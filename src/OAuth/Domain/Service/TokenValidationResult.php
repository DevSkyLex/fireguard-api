<?php

declare(strict_types=1);

namespace OAuth\Domain\Service;

/**
 * Class TokenValidationResult.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class TokenValidationResult
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * TokenValidationResult class.
   *
   * @since 1.0.0
   *
   * @param bool $valid whether the token is valid
   * @param string|null $errorCode the error code if invalid
   * @param string|null $errorMessage the error message if invalid
   * @param string|null $tokenId the token identifier
   * @param string|null $userId the user identifier
   * @param string|null $clientId the client identifier
   * @param list<string> $scopes the token scopes
   * @param int|null $expiresAt the expiration timestamp
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
  ) {
  }
  // #endregion

  // #region Factory Methods
  /**
   * Method success.
   *
   * Creates a successful validation result.
   *
   * @since 1.0.0
   *
   * @param string|null $tokenId the token identifier
   * @param string|null $userId the user identifier
   * @param string|null $clientId the client identifier
   * @param list<string> $scopes the token scopes
   * @param int|null $expiresAt the expiration timestamp
   *
   * @return self the result
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
   * Method failed.
   *
   * Creates a failed validation result.
   *
   * @since 1.0.0
   *
   * @param string $errorCode the error code
   * @param string $errorMessage the error message
   *
   * @return self the result
   */
  public static function failed(string $errorCode, string $errorMessage): self
  {
    return new self(
      valid: false,
      errorCode: $errorCode,
      errorMessage: $errorMessage,
    );
  }
  // #endregion

  // #region Methods
  /**
   * Method isExpired.
   *
   * Checks if the error is due to expiration.
   *
   * @since 1.0.0
   *
   * @return bool true if expired
   */
  public function isExpired(): bool
  {
    return TokenValidationService::VALIDATION_EXPIRED === $this->errorCode;
  }

  /**
   * Method isRevoked.
   *
   * Checks if the error is due to revocation.
   *
   * @since 1.0.0
   *
   * @return bool true if revoked
   */
  public function isRevoked(): bool
  {
    return TokenValidationService::VALIDATION_REVOKED === $this->errorCode;
  }
  // #endregion
}
