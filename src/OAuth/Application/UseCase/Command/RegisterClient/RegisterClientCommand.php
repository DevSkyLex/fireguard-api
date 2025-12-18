<?php

declare(strict_types=1);

namespace OAuth\Application\UseCase\Command\RegisterClient;

use Shared\Application\Message\CommandMessage;
use OAuth\Domain\ValueObject\GrantTypes;
use OAuth\Domain\ValueObject\RedirectUri;
use OAuth\Domain\ValueObject\Scopes;

/**
 * Command RegisterClientCommand
 * @final
 *
 * Command to register a new OAuth client.
 *
 * @category Command
 * @package OAuth\Application\Command
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RegisterClientCommand implements CommandMessage
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the 
   * RegisterClientCommand class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $name The client name.
   * @param array<RedirectUri> $redirectUris The allowed redirect URIs.
   * @param GrantTypes $grantTypes The allowed grant types.
   * @param Scopes $scopes The allowed scopes.
   */
  public function __construct(
    public readonly string $name,
    public readonly array $redirectUris,
    public readonly GrantTypes $grantTypes,
    public readonly Scopes $scopes
  ) {
  }
  //#endregion
}
