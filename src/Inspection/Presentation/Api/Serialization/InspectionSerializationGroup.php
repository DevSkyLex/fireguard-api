<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Serialization;

/**
 * Class InspectionSerializationGroup
 *
 * Names the serialization groups used by inspection API DTOs.
 *
 * @category Serialization
 */
final class InspectionSerializationGroup
{
  // #region Constants
  /**
   * Constant READ. Serializes inspection fields returned by read operations.
   */
  public const string READ = 'inspection:read';

  /**
   * Constant WRITE. Selects fields accepted by inspection write operations.
   */
  public const string WRITE = 'inspection:write';
  // #endregion
}
