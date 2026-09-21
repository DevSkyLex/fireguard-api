<?php

declare(strict_types=1);

namespace TrustedDevice\Presentation\Api\Operation;

/**
 * TrustedDevice operation names.
 *
 * @category Operation
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class TrustedDeviceOperations
{
  // #region Constants
  /**
   * Constant TRUST.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string TRUST = 'trusted_device_trust';

  /**
   * Constant LIST.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string LIST = 'trusted_device_list';

  /**
   * Constant REVOKE.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string REVOKE = 'trusted_device_revoke';

  /**
   * Constant REVOKE_ALL.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string REVOKE_ALL = 'trusted_device_revoke_all';

  /**
   * Constant ALL.
   *
   * @since 1.0.0
   *
   * @var list<string>
   */
  public const array ALL = [
    self::TRUST,
    self::LIST,
    self::REVOKE,
    self::REVOKE_ALL,
  ];
  // #endregion
}
