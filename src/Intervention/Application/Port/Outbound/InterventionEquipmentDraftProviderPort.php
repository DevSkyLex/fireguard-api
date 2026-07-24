<?php

declare(strict_types=1);

namespace Intervention\Application\Port\Outbound;

use Intervention\Application\Contract\Resource\InterventionEquipmentDraft;

/**
 * Interface InterventionEquipmentDraftProviderPort.
 *
 * @category Interface
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface InterventionEquipmentDraftProviderPort
{
  /**
   * Method equipmentDrafts.
   *
   * @since 1.0.0
   *
   * @param string $interventionId the intervention id value
   *
   * @return list<InterventionEquipmentDraft>
   */
  public function equipmentDrafts(string $interventionId): array;
}
