<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Command\Conversation\GetOrCreateConversation;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase GetOrCreateConversationCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetOrCreateConversationCommand implements CommandMessage
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $userId the acting user id value
   * @param string $organizationId the organization id value
   * @param string $subjectType the subject type value
   * @param string $subjectId the subject id value
   */
  public function __construct(
    public string $userId,
    public string $organizationId,
    public string $subjectType,
    public string $subjectId,
  ) {
  }
}
