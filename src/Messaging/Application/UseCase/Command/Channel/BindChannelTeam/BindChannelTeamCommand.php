<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Command\Channel\BindChannelTeam;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase BindChannelTeamCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class BindChannelTeamCommand implements CommandMessage
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $userId the acting user id value
   * @param string $conversationId the channel (conversation) id value
   * @param ?string $teamId the organization team identifier to bind, or null to unbind
   */
  public function __construct(
    public string $userId,
    public string $conversationId,
    public ?string $teamId,
  ) {
  }
}
