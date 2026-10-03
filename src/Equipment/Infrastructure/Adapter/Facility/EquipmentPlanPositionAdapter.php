<?php

declare(strict_types=1);

namespace Equipment\Infrastructure\Adapter\Facility;

use Doctrine\ORM\EntityManagerInterface;
use Equipment\Domain\ValueObject\PlanPosition;
use Facility\Application\Port\Outbound\FacilityEquipmentPlanPositionPort;
use Shared\Domain\Exception\InvalidValueException;

use function implode;
use function is_array;
use function is_finite;
use function json_decode;

/**
 * Adapter EquipmentPlanPositionAdapter.
 *
 * Equipment-side implementation of the Facility plan-overlay's equipment
 * read: every published equipment record, scoped to the organization, whose
 * `plan_position` JSONB references the requested attachment. A single native
 * SQL query with a JSONB `->>'attachmentId'` filter, mirroring
 * `Facility\Infrastructure\Persistence\Doctrine\Repository\FacilityRepository::findZonesForPlanAttachment()` —
 * DQL has no JSONB operator, so this cannot be expressed as a QueryBuilder
 * expression.
 *
 * @category Outbound Adapter
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class EquipmentPlanPositionAdapter implements FacilityEquipmentPlanPositionPort
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager the entity manager
   */
  public function __construct(
    private EntityManagerInterface $entityManager,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * {@inheritDoc}
   */
  public function findEquipmentPlacedOnPlan(string $organizationId, string $attachmentId): array
  {
    $sql = <<<'SQL'
      SELECT id, facility_id, type, serial_number, location_label, status, plan_position
      FROM equipment
      WHERE organization_id = :organizationId
        AND record_status = :published
        AND plan_position IS NOT NULL
        AND plan_position ->> 'attachmentId' = :attachmentId
      SQL;

    /** @var list<array{id: string, facility_id: ?string, type: string, serial_number: ?string, location_label: ?string, status: string, plan_position: string}> $rows */
    $rows = $this->entityManager->getConnection()->executeQuery($sql, [
      'organizationId' => $organizationId,
      'published' => 'published',
      'attachmentId' => $attachmentId,
    ])->fetchAllAssociative();

    $items = [];
    foreach ($rows as $row) {
      /** @var mixed $data */
      $data = json_decode($row['plan_position'], true);
      $invalidPosition = false;
      $position = null;

      try {
        if (!is_array($data)) {
          throw InvalidValueException::because('Invalid persisted equipment position.');
        }
        $validated = PlanPosition::fromArray($data);
        if (!is_finite($validated->x()) || !is_finite($validated->y())) {
          throw InvalidValueException::because('Non-finite persisted equipment position.');
        }
        $position = $validated->toArray();
      } catch (InvalidValueException) {
        $invalidPosition = true;
      }

      $items[] = [
        'equipmentId' => $row['id'],
        'facilityId' => $row['facility_id'],
        'type' => $row['type'],
        'serialNumber' => $row['serial_number'],
        'locationLabel' => $row['location_label'],
        'status' => $row['status'],
        'x' => $position['x'] ?? 0.0,
        'y' => $position['y'] ?? 0.0,
        'invalidPosition' => $invalidPosition,
      ];
    }

    return $items;
  }

  /**
   * {@inheritDoc}
   */
  public function findEquipmentForFacilities(string $organizationId, array $facilityBindings): array
  {
    if ([] === $facilityBindings) {
      return [];
    }

    $values = [];
    $parameters = ['organizationId' => $organizationId, 'published' => 'published'];
    foreach ($facilityBindings as $index => $binding) {
      $values[] = "(CAST(:floorId{$index} AS varchar), CAST(:facilityId{$index} AS varchar))";
      $parameters["floorId{$index}"] = $binding['floorId'];
      $parameters["facilityId{$index}"] = $binding['facilityId'];
    }
    $bindings = implode(', ', $values);
    $sql = <<<SQL
      WITH bindings (floor_id, facility_id) AS (VALUES {$bindings})
      SELECT bindings.floor_id, equipment.id, equipment.facility_id, equipment.type,
             equipment.serial_number, equipment.location_label, equipment.status,
             equipment.plan_position
      FROM equipment
      INNER JOIN bindings ON equipment.facility_id = bindings.facility_id
      WHERE equipment.organization_id = :organizationId
        AND equipment.record_status = :published
        AND equipment.status <> 'decommissioned'
      ORDER BY equipment.created_at ASC, equipment.id ASC
      SQL;

    /** @var list<array{floor_id: string, id: string, facility_id: string, type: string, serial_number: ?string, location_label: ?string, status: string, plan_position: ?string}> $rows */
    $rows = $this->entityManager->getConnection()->executeQuery($sql, $parameters)->fetchAllAssociative();
    $items = [];
    foreach ($rows as $row) {
      $position = null;
      $invalidPosition = false;
      if (null !== $row['plan_position']) {
        /** @var mixed $data */
        $data = json_decode($row['plan_position'], true);

        try {
          if (!is_array($data)) {
            throw InvalidValueException::because('Invalid persisted equipment position.');
          }
          $validated = PlanPosition::fromArray($data);
          if (!is_finite($validated->x()) || !is_finite($validated->y())) {
            throw InvalidValueException::because('Non-finite persisted equipment position.');
          }
          $position = $validated->toArray();
        } catch (InvalidValueException) {
          $invalidPosition = true;
        }
      }

      $items[] = [
        'floorId' => $row['floor_id'],
        'equipmentId' => $row['id'],
        'facilityId' => $row['facility_id'],
        'type' => $row['type'],
        'serialNumber' => $row['serial_number'],
        'locationLabel' => $row['location_label'],
        'status' => $row['status'],
        'position' => $position,
        'invalidPosition' => $invalidPosition,
      ];
    }

    return $items;
  }

  // #endregion
}
