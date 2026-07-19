<?php

declare(strict_types=1);

namespace Messaging\Presentation\Api\Processor\Conversation;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Messaging\Application\UseCase\Command\Conversation\UnfavoriteConversation\UnfavoriteConversationCommand;
use Messaging\Presentation\Api\Trait\MessagingExceptionMapperTrait;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};
use Throwable;

use function is_string;

/**
 * Processor UnfavoriteConversationProcessor.
 *
 * Handles `DELETE /api/conversations/{id}/favorite` (`204 No Content`,
 * idempotent — unfavoriting a conversation that was never favorited, or one
 * the member has since lost read access to, never errors; works for a
 * channel too, since a channel id IS a conversation id).
 *
 * @category Processor
 *
 * @version 1.2.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<mixed, void>
 */
final readonly class UnfavoriteConversationProcessor implements ProcessorInterface
{
  use MessagingExceptionMapperTrait;

  /**
   * Constructor.
   *
   * @since 1.2.0
   *
   * @param CommandBusPort $commandBus the command bus value
   * @param Security $security the security value
   */
  public function __construct(
    private CommandBusPort $commandBus,
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
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
  {
    $user = $this->user();
    $id = is_string($uriVariables['id'] ?? null) ? $uriVariables['id'] : null;
    if (null === $id) {
      throw new BadRequestHttpException('The id URI parameter is required.');
    }

    try {
      $this->commandBus->dispatch(new UnfavoriteConversationCommand(
        userId: $user->getId(),
        conversationId: $id,
      ));
    } catch (Throwable $exception) {
      throw $this->mapMessagingException($exception);
    }
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
