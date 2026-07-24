<?php

declare(strict_types=1);

namespace Import\Application\Port\Outbound;

/**
 * Port ImportJobQueuePort.
 *
 * Enqueues the async processing of a freshly created import job, mirroring
 * `Intervention\Application\Port\Outbound\PublicationQueuePort`: a
 * fire-and-forget dispatch onto the `async` transport, deliberately NOT
 * routed through `CommandBusPort` — that port's `dispatch()` waits for a
 * `HandledStamp` and throws `NoHandlerResultException` when the message is
 * merely sent to a transport rather than handled inline, which is exactly
 * what happens for every async-routed command.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface ImportJobQueuePort
{
  // #region Methods
  /**
   * Method dispatch.
   *
   * Enqueues `ProcessImportJobCommand` for the given import job.
   *
   * @since 1.0.0
   *
   * @param string $importJobId the import job identifier
   */
  public function dispatch(string $importJobId): void;
  // #endregion
}
