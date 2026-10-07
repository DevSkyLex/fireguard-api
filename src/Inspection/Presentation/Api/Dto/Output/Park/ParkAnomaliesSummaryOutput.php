<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Dto\Output\Park;

use Inspection\Presentation\Api\Serialization\InspectionSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Counts of published unresolved anomalies in the selected parc.
 *
 * @category DTO
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ParkAnomaliesSummaryOutput
{
  #[Groups([InspectionSerializationGroup::READ])]
  public int $openAnomalies = 0;

  /**
   * @var array{low:int,medium:int,high:int,critical:int}
   */
  #[Groups([InspectionSerializationGroup::READ])]
  public array $bySeverity = ['low' => 0, 'medium' => 0, 'high' => 0, 'critical' => 0];
}
