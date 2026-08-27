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
   * Constant ACTIVATE.
   *
   * Activate user operation name.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string ACTIVATE = 'user_activate';

  /**
   * Constant DEACTIVATE.
   *
   * Deactivate user operation name.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string DEACTIVATE = 'user_deactivate';

  /**
   * Constant VERIFY_EMAIL.
   *
   * Verify user email operation name.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string VERIFY_EMAIL = 'user_verify_email';

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
   * Constant GET_CURRENT_PROFILE.
   *
   * Get current authenticated user profile operation name.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string GET_CURRENT_PROFILE = 'user_get_current_profile';

  /**
   * Constant UPDATE_CURRENT_PROFILE.
   *
   * Update current authenticated user profile operation name.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string UPDATE_CURRENT_PROFILE = 'user_update_current_profile';

  /**
   * Constant UPLOAD_CURRENT_AVATAR.
   *
   * Upload current authenticated user avatar operation name.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string UPLOAD_CURRENT_AVATAR = 'user_upload_current_avatar';

  /**
   * Constant DEACTIVATE_CURRENT.
   *
   * Self-service deactivate current authenticated user account operation name.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string DEACTIVATE_CURRENT = 'user_deactivate_current';

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
   * Constant UPLOAD_AVATAR.
   *
   * Upload user avatar operation name.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string UPLOAD_AVATAR = 'user_upload_avatar';

  /**
   * Constant GET_AVATAR.
   *
   * Get user avatar operation name.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string GET_AVATAR = 'user_get_avatar';

  /**
   * Constant GET_AVATAR_SIZED.
   *
   * Get user avatar by size operation name.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string GET_AVATAR_SIZED = 'user_get_avatar_sized';

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
    self::GET_CURRENT_PROFILE,
    self::UPDATE_CURRENT_PROFILE,
    self::UPLOAD_CURRENT_AVATAR,
    self::DEACTIVATE_CURRENT,
    self::LIST,
    self::UPDATE,
    self::REPLACE,
    self::DELETE,
    self::ACTIVATE,
    self::DEACTIVATE,
    self::VERIFY_EMAIL,
    self::UPLOAD_AVATAR,
    self::GET_AVATAR,
    self::GET_AVATAR_SIZED,
  ];
  // #endregion
}
