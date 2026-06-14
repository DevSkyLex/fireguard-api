<?php

declare(strict_types=1);

namespace Mission\Application\Service;

use Mission\Application\Port\Outbound\MissionDraftPublisherPort;

/**
 * Service MissionDraftPublisher.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MissionDraftPublisher
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param iterable<MissionDraftPublisherPort> $publishers
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
   * @param string $missionId the mission id value
   */
  public function publish(string $missionId): void
  {
    foreach ($this->publishers as $publisher) {
      $publisher->publishDrafts($missionId);
    }
  }
}
