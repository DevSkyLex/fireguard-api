<?php

declare(strict_types=1);

namespace Import\Infrastructure\Adapter\Messenger;

use Import\Application\Port\Outbound\ImportJobQueuePort;
use Import\Application\UseCase\Command\ProcessImportJob\ProcessImportJobCommand;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Adapter MessengerImportJobQueueAdapter.
 *
 * Mirrors `Intervention\Infrastructure\Adapter\Publication\MessengerPublicationQueueAdapter`:
 * dispatches directly onto the raw Symfony Messenger bus (routed to the
 * `async` transport by `config/packages/messenger.yaml`) rather than through
 * `CommandBusPort`, which expects a `HandledStamp` that an async-routed
 * dispatch never produces.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MessengerImportJobQueueAdapter implements ImportJobQueuePort
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param MessageBusInterface $messageBus the message bus value
   */
  public function __construct(
    private MessageBusInterface $messageBus,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method dispatch.
   *
   * @since 1.0.0
   *
   * @param string $importJobId the import job identifier
   */
  public function dispatch(string $importJobId): void
  {
    $this->messageBus->dispatch(new ProcessImportJobCommand($importJobId));
  }
  // #endregion
}
