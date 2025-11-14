<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Symfony\Adapter\Inbound;

use Shared\Application\Message\QueryMessage;
use Shared\Application\Message\ResultMessage;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Infrastructure\Symfony\Exception\MessengerRuntimeException;
use Shared\Infrastructure\Symfony\Exception\NoHandlerResultException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Throwable;

/**
 * Adapter MessengerQueryBus
 * @final
 *
 * Adapter for Symfony Messenger
 *
 * @category Inbound Adapter
 * @package Shared\Infrastructure\Symfony\Adapter\Inbound
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MessengerQueryBusAdapter implements QueryBusPort
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initialize the query bus.
   *
   * @access public
   * @since 1.0.0
   *
   * @param MessageBusInterface $queryBus The query bus to use.
   */
  public function __construct(
    private readonly MessageBusInterface $queryBus
  ) {}
  //#endregion

  //#region Methods
  /**
   * Method ask
   *
   * Ask a query to the query bus.
   *
   * @access public
   * @since 1.0.0
   *
   * @param QueryMessage $query The query to ask.
   *
   * @return ResultMessage The result of the query.
   *
   * @throws MessengerRuntimeException If the query bus fails to dispatch the query.
   * @throws NoHandlerResultException If the query has no handler result.
   */
  public function ask(QueryMessage $query): ResultMessage
  {
    try {
      $envelope = $this->queryBus->dispatch(message: $query);
    }
    catch (Throwable $exception) {
      throw MessengerRuntimeException::wrap(exception: $exception);
    }

    $handledStamp = $this->extractHandledStamp(
      envelope: $envelope,
      query: $query
    );

    $result = $handledStamp->getResult();

    if (!$result instanceof ResultMessage) throw NoHandlerResultException::forMessage(
      message: $query
    );

    return $result;
  }

  /**
   * Method extractHandledStamp
   *
   * Extract the handled stamp from the envelope.
   *
   * @access private
   * @since 1.0.0
   *
   * @param Envelope $envelope The envelope to extract the handled stamp from.
   * @param QueryMessage $query The query to extract the handled stamp from.
   *
   * @return HandledStamp The handled stamp.
   *
   * @throws NoHandlerResultException If the query has no handler result.
   */
  private function extractHandledStamp(Envelope $envelope, QueryMessage $query): HandledStamp
  {
    $handledStamp = $envelope->last(stampFqcn: HandledStamp::class);

    if (!$handledStamp instanceof HandledStamp) throw NoHandlerResultException::forMessage(
      message: $query
    );

    return $handledStamp;
  }
  //#endregion
}
