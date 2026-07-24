<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Command\Conversation\GetOrCreateDirectConversation;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase GetOrCreateDirectConversationCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetOrCreateDirectConversationCommand implements CommandMessage
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $userId the acting user id value
   * @param string $organizationId the organization id value
   * @param string $otherMemberId the other organization member's identifier to start a direct conversation with
   */
  public function __construct(
    public string $userId,
    public string $organizationId,
    public string $otherMemberId,
  ) {
  }
}
