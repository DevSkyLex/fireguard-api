<?php

declare(strict_types=1);

namespace Client\Application\UseCase\Command\UpdateClientDetails;

use Shared\Application\Message\CommandMessage;
use Shared\Domain\ValueObject\RedirectUri;
use Shared\Domain\ValueObject\Scopes;

/**
 * Command UpdateClientDetailsCommand
 * @final
 *
 * Command to update an existing OAuth client's details.
 *
 * @category Command
 * @package Client\Application\UseCase\Command\UpdateClientDetails
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UpdateClientDetailsCommand implements CommandMessage
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the 
   * UpdateClientDetailsCommand class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $clientId The client ID.
   * @param string $name The new client name.
   * @param array<RedirectUri> $redirectUris The new redirect URIs.
   * @param Scopes $scopes The new scopes.
   */
  public function __construct(
    public readonly string $clientId,
    public readonly string $name,
    public readonly array $redirectUris,
    public readonly Scopes $scopes
  ) {}
  //#endregion
}
