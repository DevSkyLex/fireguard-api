<?php

declare(strict_types=1);

namespace Mission\Application\Port\Outbound;

/**
 * Interface MissionDraftPublisherPort.
 *
 * @category Interface
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface MissionDraftPublisherPort
{
  /**
   * Method publishDrafts.
   *
   * Executes the publish drafts operation.
   *
   * @since 1.0.0
   *
   * @param string $missionId the mission id value
   */
  public function publishDrafts(string $missionId): void;
}
