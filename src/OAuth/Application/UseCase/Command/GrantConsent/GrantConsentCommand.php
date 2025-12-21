<?php

declare(strict_types=1);

namespace OAuth\Application\UseCase\Command\GrantConsent;

use Shared\Application\Message\CommandMessage;

/**
 * Command GrantConsentCommand.
 *
 * @category Command
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GrantConsentCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string       $userId   the user ID
   * @param string       $clientId the client ID
   * @param list<string> $scopes   the scopes to grant
   */
  public function __construct(
    public readonly string $userId,
    public readonly string $clientId,
    public readonly array $scopes,
  ) {
  }
  // #endregion
}
