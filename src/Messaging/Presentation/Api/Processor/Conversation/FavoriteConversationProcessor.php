<?php

declare(strict_types=1);

namespace Messaging\Presentation\Api\Processor\Conversation;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Messaging\Application\UseCase\Command\Conversation\FavoriteConversation\{FavoriteConversationCommand, FavoriteConversationResult};
use Messaging\Presentation\Api\Dto\Output\ConversationOutput;
use Messaging\Presentation\Api\Factory\ConversationOutputFactory;
use Messaging\Presentation\Api\Trait\MessagingExceptionMapperTrait;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};
use Throwable;

use function is_string;

/**
 * Processor FavoriteConversationProcessor.
 *
 * Handles `POST /api/conversations/{id}/favorite` (`200 OK`, idempotent —
 * works for a channel too, since a channel id IS a conversation id).
 *
 * @category Processor
 *
 * @version 1.2.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<mixed, ConversationOutput>
 */
final readonly class FavoriteConversationProcessor implements ProcessorInterface
{
  use MessagingExceptionMapperTrait;

  /**
   * Constructor.
   *
   * @since 1.2.0
   *
   * @param CommandBusPort $commandBus the command bus value
   * @param ConversationOutputFactory $mapper the mapper value
   * @param Security $security the security value
   */
  public function __construct(
    private CommandBusPort $commandBus,
    private ConversationOutputFactory $mapper,
    private Security $security,
  ) {
  }

  /**
   * Method process.
   *
   * @since 1.2.0
   *
   * @param mixed $data the data value
   * @param Operation $operation the operation value
   * @param array<string, mixed> $uriVariables the uri variables value
   * @param array<string, mixed> $context the context value
   *
   * @return ConversationOutput the process result
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ConversationOutput
  {
    $user = $this->user();
    $id = is_string($uriVariables['id'] ?? null) ? $uriVariables['id'] : null;
    if (null === $id) {
      throw new BadRequestHttpException('The id URI parameter is required.');
    }

    try {
      /** @var FavoriteConversationResult $result */
      $result = $this->commandBus->dispatch(new FavoriteConversationCommand(
        userId: $user->getId(),
        conversationId: $id,
      ));
    } catch (Throwable $exception) {
      throw $this->mapMessagingException($exception);
    }

    // The command just succeeded, so the acting member favorited this
    // conversation — no need for a second repository round trip to resolve
    // `isFavorite` back into `true`.
    return $this->mapper->fromView($result->conversation, null, 0, true);
  }

  /**
   * Method user.
   *
   * @since 1.2.0
   *
   * @return SecurityUser the user result
   */
  private function user(): SecurityUser
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    return $user;
  }
}
