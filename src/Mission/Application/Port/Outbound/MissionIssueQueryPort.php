<?php

declare(strict_types=1);

namespace Mission\Application\Port\Outbound;

use Mission\Application\Contract\Resource\MissionIssue;

/**
 * Interface MissionIssueQueryPort.
 *
 * @category Interface
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface MissionIssueQueryPort
{
  /**
   * Method issues.
   *
   * @since 1.0.0
   *
   * @param string $missionId the mission id value
   *
   * @return list<MissionIssue>
   */
  public function issues(string $missionId): array;
}
