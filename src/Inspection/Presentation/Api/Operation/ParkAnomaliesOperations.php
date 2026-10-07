<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Operation;

/**
 * Stable operations for the equipment-scoped anomaly queue.
 *
 * @category Operation
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ParkAnomaliesOperations
{
  public const string SUMMARY = 'park_anomalies_summary_get';

  public const string LIST = 'park_anomalies_list';
}
