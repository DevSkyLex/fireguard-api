<?php

declare(strict_types=1);

namespace Mission\Application\Port\Outbound;

/**
 * Interface PublicationQueuePort.
 *
 * @category Interface
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface PublicationQueuePort
{
  /**
   * Method dispatch.
   *
   * Executes the dispatch operation.
   *
   * @since 1.0.0
   *
   * @param string $publicationId the publication id value
   */
  public function dispatch(string $publicationId): void;
}
