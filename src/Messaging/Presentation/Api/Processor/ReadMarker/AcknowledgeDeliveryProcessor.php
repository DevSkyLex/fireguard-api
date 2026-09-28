<?php

declare(strict_types=1);

namespace Messaging\Presentation\Api\Processor\ReadMarker;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Messaging\Application\UseCase\Command\ReadMarker\AcknowledgeDelivery\AcknowledgeDeliveryCommand;
use Messaging\Presentation\Api\Dto\Input\AcknowledgeDeliveryInput;
use Messaging\Presentation\Api\Dto\Output\ConversationSignalOutput;
use Messaging\Presentation\Api\Trait\MessagingExceptionMapperTrait;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};
use Throwable;

use function is_string;

/**
 * Accepts a delivery position from the authenticated conversation participant.
 *
 * @implements ProcessorInterface<mixed, ConversationSignalOutput>
 */
final readonly class AcknowledgeDeliveryProcessor implements ProcessorInterface
{
  use MessagingExceptionMapperTrait;

  public function __construct(private CommandBusPort $commandBus, private Security $security)
  {
  }

  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ConversationSignalOutput
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }
    $id = $uriVariables['id'] ?? null;
    if (!is_string($id) || '' === $id || !$data instanceof AcknowledgeDeliveryInput || '' === $data->messageId) {
      throw new BadRequestHttpException('A conversation id and message id are required.');
    }

    try {
      $this->commandBus->dispatch(new AcknowledgeDeliveryCommand($user->getId(), $id, $data->messageId));
    } catch (Throwable $exception) {
      throw $this->mapMessagingException($exception);
    }

    return new ConversationSignalOutput();
  }
}
