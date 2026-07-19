<?php

declare(strict_types=1);

namespace Import\Application\UseCase\Command\ProcessImportJob;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase ProcessImportJobCommand.
 *
 * Dispatched by `CreateImportJobHandler` (via `ImportJobQueuePort`,
 * fire-and-forget) once a `pending` import job has been persisted. Routed
 * to the `async` transport (see `config/packages/messenger.yaml`).
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ProcessImportJobCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $importJobId the import job identifier to process
   */
  public function __construct(
    public string $importJobId,
  ) {
  }
  // #endregion
}
