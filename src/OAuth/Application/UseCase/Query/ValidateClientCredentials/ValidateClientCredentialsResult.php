<?php

declare(strict_types=1);

namespace OAuth\Application\UseCase\Query\ValidateClientCredentials;

use Shared\Application\Message\ResultMessage;

/**
 * Result ValidateClientCredentialsResult.
 *
 * @category Result
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ValidateClientCredentialsResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * ValidateClientCredentialsResult class.
   *
   * @since 1.0.0
   *
   * @param bool $isValid whether the credentials are valid
   * @param string|null $clientId the client ID if valid, null otherwise
   * @param list<string>|null $allowedScopes the allowed scopes if valid, null otherwise
   * @param list<string>|null $allowedGrantTypes the allowed grant types if valid, null otherwise
   */
  public function __construct(
    public readonly bool $isValid,
    public readonly ?string $clientId = null,
    public readonly ?array $allowedScopes = null,
    public readonly ?array $allowedGrantTypes = null,
  ) {
  }
  // #endregion
}
