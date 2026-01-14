<?php

declare(strict_types=1);

namespace Session\Presentation\Api\Operation;

/**
 * Session operation names.
 *
 * @category Operation
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class SessionOperations
{
  // #region Constants
  /**
   * Constant LIST.
   *
   * List sessions operation name.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string LIST = 'session_list';

  /**
   * Constant GET.
   *
   * Get session operation name.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string GET = 'session_get';

  /**
   * Constant REVOKE.
   *
   * Revoke session operation name.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string REVOKE = 'session_revoke';

  /**
   * Constant REVOKE_ALL.
   *
   * Revoke all sessions operation name.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string REVOKE_ALL = 'session_revoke_all';

  /**
   * Constant ALL.
   *
   * All session operation names.
   *
   * @since 1.0.0
   *
   * @var list<string>
   */
  public const array ALL = [
    self::LIST,
    self::GET,
    self::REVOKE,
    self::REVOKE_ALL,
  ];
  // #endregion
}
