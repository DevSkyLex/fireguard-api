<?php

declare(strict_types=1);

namespace Mission\Infrastructure\Adapter\Publication;

use Mission\Application\Port\Outbound\PublicationQueuePort;
use Mission\Application\UseCase\Command\Publication\ExecutePublication\ExecutePublicationCommand;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Adapter MessengerPublicationQueueAdapter.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MessengerPublicationQueueAdapter implements PublicationQueuePort
{
  /**
   * Constructor.
   *
   * Initializes a new instance of the MessengerPublicationQueueAdapter class.
   *
   * @since 1.0.0
   *
   * @param MessageBusInterface $messageBus the message bus value
   */
  public function __construct(private MessageBusInterface $messageBus)
  {
  }

  /**
   * Method dispatch.
   *
   * Executes the dispatch operation.
   *
   * @since 1.0.0
   *
   * @param string $publicationId the publication id value
   */
  public function dispatch(string $publicationId): void
  {
    $this->messageBus->dispatch(new ExecutePublicationCommand($publicationId));
  }
}
