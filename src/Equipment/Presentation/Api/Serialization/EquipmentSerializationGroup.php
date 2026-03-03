<?php

declare(strict_types=1);

namespace Equipment\Presentation\Api\Serialization;

/**
 * Equipment serialization groups.
 *
 * @category Serialization
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class EquipmentSerializationGroup
{
  // #region Constants
  /**
   * Constant READ.
   *
   * Default read group.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string READ = 'equipment:read';

  /**
   * Constant WRITE.
   *
   * Default write group.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string WRITE = 'equipment:write';
  // #endregion
}
