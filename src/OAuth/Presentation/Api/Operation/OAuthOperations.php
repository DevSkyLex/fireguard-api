<?php

declare(strict_types=1);

namespace OAuth\Presentation\Api\Operation;

/**
 * OAuth operation names.
 *
 * @category Operation
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OAuthOperations
{
  // #region Constants
  /**
   * Constant TOKEN.
   *
   * Token operation name.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string TOKEN = 'token';

  /**
   * Constant INTROSPECT_TOKEN.
   *
   * Introspect token operation name.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string INTROSPECT_TOKEN = 'introspect_token';

  /**
   * Constant REVOKE_TOKEN.
   *
   * Revoke token operation name.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string REVOKE_TOKEN = 'revoke_token';

  /**
   * Constant AUTHORIZE.
   *
   * Authorization endpoint operation name.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string AUTHORIZE = 'authorize';

  /**
   * Constant USERINFO.
   *
   * Userinfo operation name.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string USERINFO = 'userinfo';

  /**
   * Constant CHECK_CONSENT.
   *
   * Check consent operation name.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string CHECK_CONSENT = 'check_consent';

  /**
   * Constant GRANT_CONSENT.
   *
   * Grant consent operation name.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string GRANT_CONSENT = 'grant_consent';

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
    self::TOKEN,
    self::INTROSPECT_TOKEN,
    self::REVOKE_TOKEN,
    self::AUTHORIZE,
    self::USERINFO,
    self::CHECK_CONSENT,
    self::GRANT_CONSENT,
  ];

  /**
   * Constant TOKEN_OPERATIONS.
   *
   * Token operation names.
   *
   * @since 1.0.0
   *
   * @var list<string>
   */
  public const array TOKEN_OPERATIONS = [
    self::TOKEN,
    self::INTROSPECT_TOKEN,
    self::REVOKE_TOKEN,
  ];
  // #endregion
}
