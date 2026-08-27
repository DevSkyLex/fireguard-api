<?php

declare(strict_types=1);

namespace Equipment\Infrastructure\Adapter\Facility;

use Doctrine\ORM\EntityManagerInterface;
use Facility\Application\Port\Outbound\FacilityEquipmentPlanPositionPort;

use function json_decode;
use function sprintf;

use const JSON_THROW_ON_ERROR;

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
 *
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
      SELECT id, type, serial_number, status, plan_position
      FROM equipment
      WHERE organization_id = :organizationId
        AND record_status = :published
        AND plan_position IS NOT NULL
        AND plan_position ->> 'attachmentId' = :attachmentId
      SQL;

    /** @var list<array{id: string, type: string, serial_number: ?string, status: string, plan_position: string}> $rows */
    $rows = $this->entityManager->getConnection()->executeQuery($sql, [
      'organizationId' => $organizationId,
      'published' => 'published',
      'attachmentId' => $attachmentId,
    ])->fetchAllAssociative();

    $items = [];
    foreach ($rows as $row) {
      /** @var array{attachmentId: string, x: float, y: float} $position */
      $position = json_decode($row['plan_position'], true, 512, JSON_THROW_ON_ERROR);

      $items[] = [
        'equipmentId' => $row['id'],
        'name' => self::label($row['type'], $row['serial_number']),
        'status' => $row['status'],
        'x' => (float) $position['x'],
        'y' => (float) $position['y'],
      ];
    }

    return $items;
  }

  /**
   * Method label.
   *
   * Builds a display label from the equipment's type and serial number,
   * mirroring `EquipmentMessagingSubjectResolverAdapter::label()` — Equipment
   * has no dedicated "name" field.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @param string $type the equipment type
   * @param ?string $serialNumber the optional serial number
   *
   * @return string the display label
   */
  private static function label(string $type, ?string $serialNumber): string
  {
    return null !== $serialNumber && '' !== $serialNumber
      ? sprintf('%s (%s)', $type, $serialNumber)
      : $type;
  }
  // #endregion
}
