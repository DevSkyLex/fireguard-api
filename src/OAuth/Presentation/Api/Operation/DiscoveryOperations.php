<?php

declare(strict_types=1);

namespace OAuth\Presentation\Api\Operation;

/**
 * Discovery operation names.
 *
 * @category Operation
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class DiscoveryOperations
{
  // #region Constants
  /**
   * Constant OPENID_CONFIGURATION.
   *
   * OpenID configuration operation name.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string OPENID_CONFIGURATION = 'openid_configuration';

  /**
   * Constant JWKS.
   *
   * JWKS operation name.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string JWKS = 'jwks';

  /**
   * Constant ALL.
   *
   * All operation names.
   *
   * @since 1.0.0
   *
   * @var list<string>
   */
  public const array ALL = [
    self::OPENID_CONFIGURATION,
    self::JWKS,
  ];
  // #endregion
}
