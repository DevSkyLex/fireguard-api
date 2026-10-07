<?php

declare(strict_types=1);

namespace Equipment\Infrastructure\Adapter\Intervention;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Intervention\Application\Contract\Publication\{InterventionEquipmentSnapshot, InterventionFactsScopeTooLarge};
use Intervention\Application\Port\Outbound\InterventionEquipmentSnapshotPort;
use ServiceRequest\Application\Port\Outbound\ServiceRequestSiteTargetPort;

use function array_key_exists;
use function array_unique;
use function array_values;
use function count;

/**
 * Class InterventionEquipmentSnapshotAdapter
 *
 * Owns bounded asset identity reads, including retired assets and archived catalog codes.
 *
 * @category Adapter
 */
final readonly class InterventionEquipmentSnapshotAdapter implements InterventionEquipmentSnapshotPort
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Shares the main transaction and resolves location ownership through the facility owner's public contract.
   *
   * @access public
   *
   * @param EntityManagerInterface $entityManager explicit main manager
   * @param ServiceRequestSiteTargetPort $sites published hierarchy reader including archived sites
   *
   * @return void
   */
  public function __construct(private EntityManagerInterface $entityManager, private ServiceRequestSiteTargetPort $sites)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method snapshots
   *
   * Omits missing, draft and foreign assets without exposing another organization's identity.
   *
   * @access public
   *
   * @param string $organizationId owning organization
   * @param list<string> $equipmentIds bounded asset identifiers
   *
   * @return array<string,InterventionEquipmentSnapshot> identities indexed by identifier
   */
  public function snapshots(string $organizationId, array $equipmentIds): array
  {
    $equipmentIds = $this->bounded($equipmentIds);
    if ([] === $equipmentIds) {
      return [];
    }
    /** @var list<array{id:string,name:?string,asset_code:?string,type:string,brand:?string,model:?string,serial_number:?string,facility_id:?string}> $rows */
    $rows = $this->entityManager->getConnection()->fetchAllAssociative("SELECT id, name, asset_code, type, brand, model, serial_number, facility_id FROM equipment WHERE organization_id = :organization AND record_status = 'published' AND id IN (:ids) ORDER BY id", ['organization' => $organizationId, 'ids' => $equipmentIds], ['ids' => ArrayParameterType::STRING]);
    $locations = [];
    $result = [];
    foreach ($rows as $row) {
      $facilityId = $row['facility_id'];
      if (null !== $facilityId && !array_key_exists($facilityId, $locations)) {
        $locations[$facilityId] = $this->sites->find($organizationId, null, $facilityId);
      }
      $location = null === $facilityId ? null : $locations[$facilityId];
      $result[$row['id']] = new InterventionEquipmentSnapshot($row['id'], $row['name'], $row['asset_code'], $row['type'], $row['brand'], $row['model'], $row['serial_number'], $facilityId, null === $location ? null : ['id' => $location->id, 'name' => $location->name], $location?->customer);
    }

    return $result;
  }

  /**
   * Method equipmentIdsInFacilities
   *
   * Refuses an oversized live target scope rather than scanning or truncating silently.
   *
   * @access public
   *
   * @param string $organizationId owning organization
   * @param list<string> $facilityIds bounded location identifiers
   *
   * @return list<string> same-organization published asset identifiers
   */
  public function equipmentIdsInFacilities(string $organizationId, array $facilityIds): array
  {
    $facilityIds = $this->bounded($facilityIds);
    if ([] === $facilityIds) {
      return [];
    }
    /** @var list<string> $ids */
    $ids = $this->entityManager->getConnection()->fetchFirstColumn("SELECT id FROM equipment WHERE organization_id = :organization AND record_status = 'published' AND facility_id IN (:facilities) ORDER BY id LIMIT 10001", ['organization' => $organizationId, 'facilities' => $facilityIds], ['facilities' => ArrayParameterType::STRING]);

    return $this->bounded($ids);
  }

  /**
   * Method bounded
   *
   * Prevents unbounded reference queries while preserving unique source identity.
   *
   * @access private
   *
   * @param list<string> $ids requested identifiers
   *
   * @return list<string> unique bounded identifiers
   */
  private function bounded(array $ids): array
  {
    $ids = array_values(array_unique($ids));
    if (count($ids) > 10000) {
      throw new InterventionFactsScopeTooLarge('The equipment identity scope exceeds 10000 records; narrow the requested scope.');
    }

    return $ids;
  }
  // #endregion
}
