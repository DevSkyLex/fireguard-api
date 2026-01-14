<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Operation;

/**
 * Auth operation names.
 *
 * @category Operation
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class AuthOperations
{
  // #region Constants
  /**
   * Constant LOGIN.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string LOGIN = 'login';

  /**
   * Constant REFRESH.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string REFRESH = 'refresh';

  /**
   * Constant LOGOUT.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string LOGOUT = 'logout';

  /**
   * Constant MFA_VERIFY.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string MFA_VERIFY = 'mfa_verify';

  /**
   * Constant ALL.
   *
   * @since 1.0.0
   *
   * @var list<string>
   */
  public const array ALL = [
    self::LOGIN,
    self::REFRESH,
    self::LOGOUT,
    self::MFA_VERIFY,
  ];
  // #endregion
}
