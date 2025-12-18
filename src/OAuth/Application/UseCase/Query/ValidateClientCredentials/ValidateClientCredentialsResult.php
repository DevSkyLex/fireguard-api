<?php

declare(strict_types=1);

namespace OAuth\Application\UseCase\Query\ValidateClientCredentials;

use Shared\Application\Message\ResultMessage;

/**
 * Result ValidateClientCredentialsResult
 * @final
 *
 * Result of client credentials validation.
 *
 * @category Result
 * @package OAuth\Application\UseCase\Query\ValidateClientCredentials
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ValidateClientCredentialsResult implements ResultMessage
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the 
   * ValidateClientCredentialsResult class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param bool $isValid Whether the credentials are valid.
   * @param string|null $clientId The client ID if valid, null otherwise.
   * @param list<string>|null $allowedScopes The allowed scopes if valid, null otherwise.
   * @param list<string>|null $allowedGrantTypes The allowed grant types if valid, null otherwise.
   */
  public function __construct(
    public readonly bool $isValid,
    public readonly ?string $clientId = null,
    public readonly ?array $allowedScopes = null,
    public readonly ?array $allowedGrantTypes = null
  ) {}
  //#endregion
}
