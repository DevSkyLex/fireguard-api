<?php

declare(strict_types=1);

namespace Mission\Application\Port\Outbound;

use Mission\Application\Contract\Resource\MissionEquipmentDraft;

/**
 * Interface MissionEquipmentDraftProviderPort.
 *
 * @category Interface
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface MissionEquipmentDraftProviderPort
{
  /**
   * Method equipmentDrafts.
   *
   * @since 1.0.0
   *
   * @param string $missionId the mission id value
   *
   * @return list<MissionEquipmentDraft>
   */
  public function equipmentDrafts(string $missionId): array;
}
