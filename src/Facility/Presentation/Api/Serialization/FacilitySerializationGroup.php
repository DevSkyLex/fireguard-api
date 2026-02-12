<?php

declare(strict_types=1);

namespace Facility\Presentation\Api\Serialization;

/**
 * Facility serialization groups.
 *
 * @category Serialization
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class FacilitySerializationGroup
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
  public const string READ = 'facility:read';

  /**
   * Constant WRITE.
   *
   * Default write group.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string WRITE = 'facility:write';
  // #endregion
}
