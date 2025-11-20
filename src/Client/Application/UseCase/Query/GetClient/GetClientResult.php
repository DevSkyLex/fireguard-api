<?php

declare(strict_types=1);

namespace Client\Application\UseCase\Query\GetClient;

use Shared\Application\Message\ResultMessage;

/**
 * Result GetClientResult
 * @final
 *
 * Result containing client information.
 *
 * @category Result
 * @package Client\Application\UseCase\Query\GetClient
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetClientResult implements ResultMessage
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the 
   * GetClientResult class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $id The client ID.
   * @param string $name The client name.
   * @param list<string> $redirectUris The allowed redirect URIs.
   * @param list<string> $grantTypes The allowed grant types.
   * @param list<string> $scopes The allowed scopes.
   * @param bool $isActive Whether the client is active.
   * @param string $createdAt The creation timestamp.
   */
  public function __construct(
    public readonly string $id,
    public readonly string $name,
    public readonly array $redirectUris,
    public readonly array $grantTypes,
    public readonly array $scopes,
    public readonly bool $isActive,
    public readonly string $createdAt
  ) {}
  //#endregion
}
