<?php

declare(strict_types=1);

namespace Messaging\Presentation\Api\Processor\Message;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Messaging\Application\UseCase\Command\Message\AddReaction\{AddReactionCommand, AddReactionResult};
use Messaging\Presentation\Api\Dto\Input\AddReactionInput;
use Messaging\Presentation\Api\Dto\Output\MessageOutput;
use Messaging\Presentation\Api\Factory\MessageOutputFactory;
use Messaging\Presentation\Api\Trait\MessagingExceptionMapperTrait;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};
use Throwable;

use function is_string;

/**
 * Processor AddReactionProcessor.
 *
 * Handles `POST /api/messages/{id}/reactions` (`200 OK`, idempotent — the
 * reacting member is always the authenticated caller, never a request
 * field).
 *
 * @category Processor
 *
 * @version 1.1.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<AddReactionInput, MessageOutput>
 */
final readonly class AddReactionProcessor implements ProcessorInterface
{
  use MessagingExceptionMapperTrait;

  /**
   * Constructor.
   *
   * @since 1.1.0
   *
   * @param CommandBusPort $commandBus the command bus value
   * @param MessageOutputFactory $mapper the mapper value
   * @param Security $security the security value
   */
  public function __construct(
    private CommandBusPort $commandBus,
    private MessageOutputFactory $mapper,
    private Security $security,
  ) {
  }

  /**
   * Method process.
   *
   * @since 1.1.0
   *
   * @param mixed $data the data value
   * @param Operation $operation the operation value
   * @param array<string, mixed> $uriVariables the uri variables value
   * @param array<string, mixed> $context the context value
   *
   * @return MessageOutput the process result
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MessageOutput
  {
    $user = $this->user();
    $id = is_string($uriVariables['id'] ?? null) ? $uriVariables['id'] : null;
    if (null === $id) {
      throw new BadRequestHttpException('The id URI parameter is required.');
    }

    if (!$data instanceof AddReactionInput) {
      throw new BadRequestHttpException('Invalid request body.');
    }

    try {
      /** @var AddReactionResult $result */
      $result = $this->commandBus->dispatch(new AddReactionCommand(
        userId: $user->getId(),
        messageId: $id,
        emoji: $data->emoji,
      ));
    } catch (Throwable $exception) {
      throw $this->mapMessagingException($exception);
    }

    return $this->mapper->fromView($result->message, $result->currentMemberId);
  }

  /**
   * Method user.
   *
   * @since 1.1.0
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
