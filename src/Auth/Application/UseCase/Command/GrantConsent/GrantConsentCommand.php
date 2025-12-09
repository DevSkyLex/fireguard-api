<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Command\GrantConsent;

use Shared\Application\Message\CommandMessage;

/**
 * Command GrantConsentCommand
 * @final
 *
 * Command to grant user consent for OAuth2 authorization.
 *
 * @category Command
 * @package Auth\Application\UseCase\Command\GrantConsent
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GrantConsentCommand implements CommandMessage
{
  //#region Constructor
  /**
   * Constructor
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $userId The user ID.
   * @param string $clientId The client ID.
   * @param list<string> $scopes The scopes to grant.
   */
  public function __construct(
    public readonly string $userId,
    public readonly string $clientId,
    public readonly array $scopes,
  ) {}
  //#endregion
}
