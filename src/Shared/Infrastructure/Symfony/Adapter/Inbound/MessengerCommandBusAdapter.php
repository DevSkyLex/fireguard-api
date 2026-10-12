<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Symfony\Adapter\Inbound;

use Shared\Application\Contract\Exception\MessengerRuntimeException;
use Shared\Application\Message\{CommandMessage, ResultMessage, VoidResult};
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Infrastructure\Exception\NoHandlerResultException;
use Shared\Infrastructure\Idempotency\{HttpIdempotencyCommandPreparation, HttpIdempotencyExecutionContext};
use Symfony\Component\Messenger\{Envelope, MessageBusInterface};
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Throwable;

/**
 * Adapter MessengerCommandBus.
 *
 * @category Inbound Adapter
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
   * @param HttpIdempotencyExecutionContext|null $idempotency the current authorized HTTP command boundary
   * @param HttpIdempotencyCommandPreparation|null $preparation exact typed sensitive-owner proof preparation
   */
  public function __construct(
    private readonly MessageBusInterface $commandBus,
    private ?HttpIdempotencyExecutionContext $idempotency = null,
    private ?HttpIdempotencyCommandPreparation $preparation = null,
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
   * @return ResultMessage the result of the command
   *
   * @throws MessengerRuntimeException if the command bus fails to dispatch the command
   * @throws NoHandlerResultException if the command has no handler result
   */
  public function dispatch(CommandMessage $command): ResultMessage
  {
    $this->preparation?->prepare($command);

    return null === $this->idempotency
      ? $this->dispatchNormally($command)
      : $this->idempotency->dispatchPrimary($command, fn (): ResultMessage => $this->dispatchNormally($command));
  }

  /**
   * Method dispatchNormally
   *
   * Retains the normal message dispatch, result validation and failure translation for internal and nested commands.
   *
   * @access private
   *
   * @param CommandMessage $command the command to dispatch
   *
   * @return ResultMessage the handler's application result
   */
  private function dispatchNormally(CommandMessage $command): ResultMessage
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

    if (null === $result) {
      return new VoidResult();
    }

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
   * @return HandledStamp the handled stamp
   *
   * @throws NoHandlerResultException if the command has no handler result
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
