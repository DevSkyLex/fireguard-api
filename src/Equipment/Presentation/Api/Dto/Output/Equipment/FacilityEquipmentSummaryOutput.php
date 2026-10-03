<?php

declare(strict_types=1);

namespace Equipment\Presentation\Api\Dto\Output\Equipment;

use ApiPlatform\Metadata\ApiProperty;

/**
 * DTO FacilityEquipmentSummaryOutput.
 *
 * @category DTO
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class FacilityEquipmentSummaryOutput
{
  // #region Properties
  /**
   * Property scope.
   *
   * @since 1.0.0
   */
  #[ApiProperty(readable: true, writable: false, openapiContext: ['type' => 'string', 'enum' => ['subtree', 'direct']])]
  public string $scope = 'subtree';

  /**
   * Property totalItems.
   *
   * @since 1.0.0
   */
  #[ApiProperty(readable: true, writable: false, openapiContext: ['type' => 'integer', 'minimum' => 0])]
  public int $totalItems = 0;

  /**
   * Property byStatus.
   *
   * @since 1.0.0
   *
   * @var array{in_stock: int, operational: int, under_maintenance: int, decommissioned: int}
   */
  #[ApiProperty(readable: true, writable: false, openapiContext: [
    'type' => 'object',
    'required' => ['in_stock', 'operational', 'under_maintenance', 'decommissioned'],
    'properties' => [
      'in_stock' => ['type' => 'integer', 'minimum' => 0],
      'operational' => ['type' => 'integer', 'minimum' => 0],
      'under_maintenance' => ['type' => 'integer', 'minimum' => 0],
      'decommissioned' => ['type' => 'integer', 'minimum' => 0],
    ],
  ])]
  public array $byStatus = ['in_stock' => 0, 'operational' => 0, 'under_maintenance' => 0, 'decommissioned' => 0];

  /**
   * Property needingAttentionCount.
   *
   * @since 1.0.0
   */
  #[ApiProperty(readable: true, writable: false, openapiContext: ['type' => 'integer', 'minimum' => 0])]
  public int $needingAttentionCount = 0;
  // #endregion
}
