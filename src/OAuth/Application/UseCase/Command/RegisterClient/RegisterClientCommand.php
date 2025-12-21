<?php

declare(strict_types=1);

namespace OAuth\Application\UseCase\Command\RegisterClient;

use OAuth\Domain\ValueObject\GrantTypes;
use OAuth\Domain\ValueObject\RedirectUri;
use OAuth\Domain\ValueObject\Scopes;
use Shared\Application\Message\CommandMessage;

/**
 * Command RegisterClientCommand.
 *
 * @category Command
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RegisterClientCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * RegisterClientCommand class.
   *
   * @since 1.0.0
   *
   * @param string             $name         the client name
   * @param array<RedirectUri> $redirectUris the allowed redirect URIs
   * @param GrantTypes         $grantTypes   the allowed grant types
   * @param Scopes             $scopes       the allowed scopes
   */
  public function __construct(
    public readonly string $name,
    public readonly array $redirectUris,
    public readonly GrantTypes $grantTypes,
    public readonly Scopes $scopes,
  ) {
  }
  // #endregion
}
