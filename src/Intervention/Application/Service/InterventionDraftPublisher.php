<?php

declare(strict_types=1);

namespace Intervention\Application\Service;

use Intervention\Application\Port\Outbound\InterventionDraftPublisherPort;

/**
 * Service InterventionDraftPublisher.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InterventionDraftPublisher
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param iterable<InterventionDraftPublisherPort> $publishers
   */
  public function __construct(private iterable $publishers)
  {
  }

  /**
   * Method publish.
   *
   * Executes the publish operation.
   *
   * @since 1.0.0
   *
   * @param string $interventionId the intervention id value
   */
  public function publish(string $interventionId): void
  {
    foreach ($this->publishers as $publisher) {
      $publisher->publishDrafts($interventionId);
    }
  }

  /**
   * Method discard.
   *
   * Discards every owning module's still-draft resource records for an
   * intervention that is abandoned or deleted, preventing orphaned drafts.
   *
   * @since 1.0.0
   *
   * @param string $interventionId the intervention id value
   */
  public function discard(string $interventionId): void
  {
    foreach ($this->publishers as $publisher) {
      $publisher->discardDrafts($interventionId);
    }
  }
}
