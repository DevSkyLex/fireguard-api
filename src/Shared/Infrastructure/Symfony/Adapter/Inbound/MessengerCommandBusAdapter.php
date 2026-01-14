<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Symfony\Adapter\Inbound;

use Shared\Application\Message\{CommandMessage, ResultMessage};
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Infrastructure\Exception\{MessengerRuntimeException, NoHandlerResultException};
use Symfony\Component\Messenger\{Envelope, MessageBusInterface};
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Throwable;

/**
 * Adapter MessengerCommandBus.
 *
 * @category Inbound Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MessengerCommandBusAdapter implements CommandBusPort
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initialize the command bus.
   *
   * @since 1.0.0
   *
   * @param MessageBusInterface $commandBus the command bus to use
   */
  public function __construct(
    private readonly MessageBusInterface $commandBus,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method dispatch.
   *
   * Dispatch a command message and
   * return its result message.
   *
   * @since 1.0.0
   *
   * @param CommandMessage $command the command to dispatch
   *
   * @throws MessengerRuntimeException if the command bus fails to dispatch the command
   * @throws NoHandlerResultException if the command has no handler result
   *
   * @return ResultMessage the result of the command
   */
  public function dispatch(CommandMessage $command): ResultMessage
  {
    try {
      $envelope = $this->commandBus->dispatch(message: $command);
    } catch (Throwable $exception) {
      throw MessengerRuntimeException::wrap(exception: $exception);
    }

    $handledStamp = $this->extractHandledStamp(
      envelope: $envelope,
      command: $command,
    );

    $result = $handledStamp->getResult();

    if (!$result instanceof ResultMessage) {
      throw NoHandlerResultException::forMessage(
        message: $command,
      );
    }

    return $result;
  }

  /**
   * Method extractHandledStamp.
   *
   * Extract the handled stamp from the envelope.
   *
   * @since 1.0.0
   *
   * @param Envelope $envelope the envelope to extract the handled stamp from
   * @param CommandMessage $command the command to extract the handled stamp from
   *
   * @throws NoHandlerResultException if the command has no handler result
   *
   * @return HandledStamp the handled stamp
   */
  private function extractHandledStamp(Envelope $envelope, CommandMessage $command): HandledStamp
  {
    $handledStamp = $envelope->last(stampFqcn: HandledStamp::class);

    if (!$handledStamp instanceof HandledStamp) {
      throw NoHandlerResultException::forMessage(
        message: $command,
      );
    }

    return $handledStamp;
  }
  // #endregion
}
