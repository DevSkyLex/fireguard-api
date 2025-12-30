<?php

declare(strict_types=1);

namespace OAuth\Application\UseCase\Query\Client\GetClient;

use Shared\Application\Message\ResultMessage;

/**
 * Result GetClientResult.
 *
 * @category Result
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetClientResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * GetClientResult class.
   *
   * @since 1.0.0
   *
   * @param string $id the client ID
   * @param string $name the client name
   * @param list<string> $redirectUris the allowed redirect URIs
   * @param list<string> $grantTypes the allowed grant types
   * @param list<string> $scopes the allowed scopes
   * @param bool $isActive whether the client is active
   * @param string $createdAt the creation timestamp
   */
  public function __construct(
    public readonly string $id,
    public readonly string $name,
    public readonly array $redirectUris,
    public readonly array $grantTypes,
    public readonly array $scopes,
    public readonly bool $isActive,
    public readonly string $createdAt,
  ) {
  }
  // #endregion
}
