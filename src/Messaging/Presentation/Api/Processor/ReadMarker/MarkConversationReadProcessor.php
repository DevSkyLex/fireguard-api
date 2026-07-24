<?php

declare(strict_types=1);

namespace Messaging\Presentation\Api\Processor\ReadMarker;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Messaging\Application\UseCase\Command\ReadMarker\MarkConversationRead\{MarkConversationReadCommand, MarkConversationReadResult};
use Messaging\Presentation\Api\Dto\Input\MarkConversationReadInput;
use Messaging\Presentation\Api\Dto\Output\ConversationOutput;
use Messaging\Presentation\Api\Factory\ConversationOutputFactory;
use Messaging\Presentation\Api\Trait\MessagingExceptionMapperTrait;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};
use Throwable;

use function is_string;

/**
 * Processor MarkConversationReadProcessor.
 *
 * Handles `PATCH /api/conversations/{id}/read`.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<MarkConversationReadInput, ConversationOutput>
 */
final readonly class MarkConversationReadProcessor implements ProcessorInterface
{
  use MessagingExceptionMapperTrait;

  /**
   * Constructor.
   *
   * @since 1.0.0
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
   * @since 1.0.0
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

    $lastReadMessageId = $data instanceof MarkConversationReadInput ? $data->lastReadMessageId : null;

    try {
      /** @var MarkConversationReadResult $result */
      $result = $this->commandBus->dispatch(new MarkConversationReadCommand(
        userId: $user->getId(),
        conversationId: $id,
        lastReadMessageId: $lastReadMessageId,
      ));
    } catch (Throwable $exception) {
      throw $this->mapMessagingException($exception);
    }

    return $this->mapper->fromView($result->conversation, null, 0);
  }

  /**
   * Method user.
   *
   * @since 1.0.0
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
