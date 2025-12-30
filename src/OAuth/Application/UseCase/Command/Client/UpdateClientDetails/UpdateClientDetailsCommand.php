<?php

declare(strict_types=1);

namespace OAuth\Application\UseCase\Command\Client\UpdateClientDetails;

use OAuth\Domain\ValueObject\Client\RedirectUri;
use OAuth\Domain\ValueObject\Scope\Scopes;
use Shared\Application\Message\CommandMessage;

/**
 * Command UpdateClientDetailsCommand.
 *
 * @category Command
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UpdateClientDetailsCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * UpdateClientDetailsCommand class.
   *
   * @since 1.0.0
   *
   * @param string $clientId the client ID
   * @param string $name the new client name
   * @param array<RedirectUri> $redirectUris the new redirect URIs
   * @param Scopes $scopes the new scopes
   */
  public function __construct(
    public readonly string $clientId,
    public readonly string $name,
    public readonly array $redirectUris,
    public readonly Scopes $scopes,
  ) {
  }
  // #endregion
}
