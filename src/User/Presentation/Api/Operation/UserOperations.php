<?php

declare(strict_types=1);

namespace User\Presentation\Api\Operation;

/**
 * User operation names.
 *
 * @category Operation
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class UserOperations
{
  // #region Constants
  /**
   * Constant CREATE.
   *
   * Create user operation name.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string CREATE = 'user_create';

  /**
   * Constant GET.
   *
   * Get user operation name.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string GET = 'user_get';

  /**
   * Constant LIST.
   *
   * List users operation name.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string LIST = 'user_list';

  /**
   * Constant UPDATE.
   *
   * Update user operation name.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string UPDATE = 'user_update';

  /**
   * Constant REPLACE.
   *
   * Replace user operation name.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string REPLACE = 'user_replace';

  /**
   * Constant DELETE.
   *
   * Delete user operation name.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string DELETE = 'user_delete';

  /**
   * Constant ALL.
   *
   * All user operation names.
   *
   * @since 1.0.0
   *
   * @var list<string>
   */
  public const array ALL = [
    self::CREATE,
    self::GET,
    self::LIST,
    self::UPDATE,
    self::REPLACE,
    self::DELETE,
  ];
  // #endregion
}
