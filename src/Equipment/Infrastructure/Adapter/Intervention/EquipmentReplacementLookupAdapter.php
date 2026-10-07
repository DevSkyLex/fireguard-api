<?php

declare(strict_types=1);

namespace Equipment\Infrastructure\Adapter\Intervention;

use Doctrine\ORM\EntityManagerInterface;
use Intervention\Application\Port\Outbound\InterventionEquipmentReplacementPort;

use function is_numeric;

/**
 * Class EquipmentReplacementLookupAdapter
 *
 * Confirms the persisted symmetric replacement link before a work result can cite its successor.
 *
 * @category Adapter
 */
final readonly class EquipmentReplacementLookupAdapter implements InterventionEquipmentReplacementPort
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param EntityManagerInterface $entityManager the explicitly wired main entity manager
   *
   * @return void
   */
  public function __construct(private EntityManagerInterface $entityManager)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method isReplacement
   *
   * @access public
   *
   * @param string $organizationId the publication owner
   * @param string $originalEquipmentId the retired historical asset
   * @param string $successorEquipmentId the actual replacement asset
   *
   * @return bool whether both persisted links agree within the owner
   */
  public function isReplacement(string $organizationId, string $originalEquipmentId, string $successorEquipmentId): bool
  {
    $count = $this->entityManager->getConnection()->fetchOne(
      'SELECT COUNT(*) FROM equipment predecessor INNER JOIN equipment successor ON successor.id = predecessor.successor_equipment_id AND successor.predecessor_equipment_id = predecessor.id WHERE predecessor.organization_id = :organization AND successor.organization_id = :organization AND predecessor.id = :predecessor AND successor.id = :successor AND predecessor.record_status = :published AND successor.record_status = :published AND predecessor.status = :retired',
      [
        'organization' => $organizationId, 'predecessor' => $originalEquipmentId,
        'successor' => $successorEquipmentId, 'published' => 'published', 'retired' => 'decommissioned',
      ],
    );

    return is_numeric($count) && (int) $count > 0;
  }
  // #endregion
}
