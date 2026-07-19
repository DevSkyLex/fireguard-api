<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Command\Conversation\GetOrCreateConversation;

use Messaging\Application\Port\Outbound\MessagingConversationRepositoryPort;
use Messaging\Application\Service\{MessagingAccessPolicy, MessagingSubjectResolverRegistry};
use Messaging\Domain\Exception\MessagingValidationException;
use Messaging\Domain\ValueObject\{ConversationVisibility, MessagingSubjectType};
use Shared\Application\Message\CommandHandler;
use ValueError;

use function sprintf;

/**
 * UseCase GetOrCreateConversationHandler.
 *
 * Idempotent get-or-create by subject: resolves and validates the subject
 * through the tagged resolver seam, asserts the caller can read the thread
 * (subject read permission + `organization.messaging.read`), then delegates
 * to the repository's atomic get-or-create.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetOrCreateConversationHandler implements CommandHandler
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param MessagingSubjectResolverRegistry $resolvers the subject resolver registry
   * @param MessagingAccessPolicy $accessPolicy the messaging access policy
   * @param MessagingConversationRepositoryPort $conversations the conversation repository port
   */
  public function __construct(
    private MessagingSubjectResolverRegistry $resolvers,
    private MessagingAccessPolicy $accessPolicy,
    private MessagingConversationRepositoryPort $conversations,
  ) {
  }

  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param GetOrCreateConversationCommand $command the command value
   *
   * @return GetOrCreateConversationResult the use case result
   */
  public function __invoke(GetOrCreateConversationCommand $command): GetOrCreateConversationResult
  {
    try {
      $subjectType = MessagingSubjectType::from($command->subjectType);
    } catch (ValueError) {
      throw new MessagingValidationException(sprintf('Unsupported subject type "%s".', $command->subjectType));
    }

    $resolution = $this->resolvers->resolve($subjectType, $command->organizationId, $command->subjectId);
    $this->accessPolicy->assertCanReadThread($command->userId, $command->organizationId, $resolution->requiredReadPermission);

    $view = $this->conversations->getOrCreate($command->organizationId, $subjectType, $command->subjectId, ConversationVisibility::SUBJECT);

    return new GetOrCreateConversationResult($view, $resolution->label);
  }
}
