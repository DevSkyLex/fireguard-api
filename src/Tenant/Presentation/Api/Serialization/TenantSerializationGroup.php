<?php

declare(strict_types=1);

namespace Tenant\Presentation\Api\Serialization;

/**
 * Tenant serialization groups.
 *
 * @category Serialization
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class TenantSerializationGroup
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
  public const string READ = 'tenant:read';

  /**
   * Constant WRITE.
   *
   * Default write group.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string WRITE = 'tenant:write';

  /**
   * Constant SETTINGS.
   *
   * Tenant settings group.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string SETTINGS = 'tenant:settings';
  // #endregion
}
