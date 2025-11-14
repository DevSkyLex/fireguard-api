<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Symfony\Adapter\Inbound;

use Shared\Application\Message\ResultMessage;
use Shared\Application\Port\Inbound\EventListenerPort;
use Shared\Infrastructure\Symfony\Exception\MessengerRuntimeException;
use Shared\Infrastructure\Symfony\Exception\NoHandlerResultException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Throwable;

/**
 * Adapter MessengerEventListener
 * @implements EventListenerPort
 * @final
 *
 * Adapter for handling incoming events through
 * Symfony Messenger.
 *
 * @category Inbound Adapter
 * @package Shared\Infrastructure\Symfony\Adapter\Inbound
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MessengerEventListenerAdapter implements EventListenerPort
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initialize the event listener bus.
   *
   * @access public
   * @since 1.0.0
   *
   * @param MessageBusInterface $eventBus The message bus receiving events.
   */
  public function __construct(
    private readonly MessageBusInterface $eventBus
  ) {}
  //#endregion

  //#region Methods
  /**
   * {@inheritDoc}
   */
  public function handle(object $event): ?ResultMessage
  {
    try {
      $envelope = $this->eventBus->dispatch(message: $event);
    }
    catch (Throwable $exception) {
      throw MessengerRuntimeException::wrap(exception: $exception);
    }

    $handledStamp = $this->extractHandledStamp(
      envelope: $envelope
    );

    if ($handledStamp === null) {
      return null;
    }

    $result = $handledStamp->getResult();

    if ($result === null) {
      return null;
    }

    if (!$result instanceof ResultMessage) {
      throw NoHandlerResultException::forMessage(message: $event);
    }

    return $result;
  }

  /**
   * Method extractHandledStamp
   * @method extractHandledStamp(Envelope $envelope): ?HandledStamp
   *
   * Extract the last handled stamp from the envelope.
   *
   * @access private
   * @since 1.0.0
   *
   * @param Envelope $envelope The envelope to inspect.
   *
   * @return ?HandledStamp The handled stamp if present, null otherwise.
   */
  private function extractHandledStamp(Envelope $envelope): ?HandledStamp
  {
    $handledStamp = $envelope->last(stampFqcn: HandledStamp::class);

    if (!$handledStamp instanceof HandledStamp) {
      return null;
    }

    return $handledStamp;
  }
  //#endregion
}
