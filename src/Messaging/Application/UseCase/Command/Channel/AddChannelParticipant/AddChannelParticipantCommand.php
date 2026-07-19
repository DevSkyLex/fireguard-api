<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Command\Channel\AddChannelParticipant;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase AddChannelParticipantCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AddChannelParticipantCommand implements CommandMessage
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $userId the acting user id value
   * @param string $conversationId the channel (conversation) id value
   * @param string $memberId the organization member identifier to add
   * @param ?string $role the free-form membership label, if any
   */
  public function __construct(
    public string $userId,
    public string $conversationId,
    public string $memberId,
    public ?string $role = null,
  ) {
  }
}
