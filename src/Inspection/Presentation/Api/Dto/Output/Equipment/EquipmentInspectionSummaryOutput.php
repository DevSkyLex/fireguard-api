<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Dto\Output\Equipment;

use ApiPlatform\Metadata\ApiProperty;

/**
 * DTO EquipmentInspectionSummaryOutput.
 *
 * @category DTO
 */
final class EquipmentInspectionSummaryOutput
{
  #[ApiProperty(readable: true, writable: false)]
  public string $equipmentId = '';

  #[ApiProperty(readable: true, writable: false)]
  public int $openAnomalies = 0;

  /**
   * @var array{low: int, medium: int, high: int, critical: int}
   */
  #[ApiProperty(readable: true, writable: false)]
  public array $bySeverity = ['low' => 0, 'medium' => 0, 'high' => 0, 'critical' => 0];

  #[ApiProperty(readable: true, writable: false)]
  public ?string $lastInspectionId = null;

  #[ApiProperty(readable: true, writable: false)]
  public ?string $lastInspectionPerformedAt = null;

  #[ApiProperty(readable: true, writable: false)]
  public ?string $lastInspectionResult = null;
}
