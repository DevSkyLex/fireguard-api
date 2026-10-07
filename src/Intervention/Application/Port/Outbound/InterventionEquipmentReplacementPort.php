<?php

declare(strict_types=1);

namespace Intervention\Application\Port\Outbound;

/**
 * Interface InterventionEquipmentReplacementPort
 *
 * Checks durable owner replacement links before validating replacement work.
 *
 * @category Port
 */
interface InterventionEquipmentReplacementPort
{
  /**
   * Method isReplacement
   *
   * Returns false for foreign, missing, unpublished or unrelated equipment pairs.
   *
   * @access public
   *
   * @param string $organizationId owning organization
   * @param string $originalEquipmentId retired predecessor
   * @param string $successorEquipmentId published replacement
   *
   * @return bool whether the owner confirms this replacement
   */
  public function isReplacement(string $organizationId, string $originalEquipmentId, string $successorEquipmentId): bool;
}
