<?php

declare(strict_types=1);

namespace Messaging\Presentation\Api\Processor\ReadMarker;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Messaging\Application\UseCase\Command\ReadMarker\AcknowledgeDelivery\AcknowledgeDeliveryCommand;
use Messaging\Presentation\Api\Dto\Input\AcknowledgeDeliveryInput;
use Messaging\Presentation\Api\Dto\Output\ConversationSignalOutput;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Application\Port\Outbound\CurrentActorPort;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};

use function is_string;

/**
 * Accepts a delivery position from the authenticated conversation participant.
 *
 * @implements ProcessorInterface<mixed, ConversationSignalOutput>
 */
final readonly class AcknowledgeDeliveryProcessor implements ProcessorInterface
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Initializes the AcknowledgeDeliveryProcessor dependencies and state.
   *
   * @access public
   *
   * @param CommandBusPort $commandBus the command bus
   * @param CurrentActorPort $actor the actor
   *
   * @return void
   */
  public function __construct(private CommandBusPort $commandBus, private CurrentActorPort $actor)
  {
  }

  // #endregion
  // #region Methods
  /**
   * Method process
   *
   * Processes the API operation by translating the request into the corresponding application action.
   *
   * @access public
   *
   * @param mixed $data the data
   * @param Operation $operation the operation
   * @param array<string, mixed> $uriVariables the optional uri variables
   * @param array<string, mixed> $context the optional context
   *
   * @return ConversationSignalOutput
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ConversationSignalOutput
  {
    $userId = $this->actor->userId();
    if (null === $userId) {
      throw new AccessDeniedHttpException('Authentication required.');
    }
    $id = $uriVariables['id'] ?? null;
    if (!is_string($id) || '' === $id || !$data instanceof AcknowledgeDeliveryInput || '' === $data->messageId) {
      throw new BadRequestHttpException('A conversation id and message id are required.');
    }

    $this->commandBus->dispatch(new AcknowledgeDeliveryCommand($userId, $id, $data->messageId));

    return new ConversationSignalOutput();
  }
  // #endregion
}
