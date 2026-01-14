<?php

declare(strict_types=1);

namespace Authorization\Presentation\Api\Operation;

/**
 * Authorization operation names.
 *
 * @category Operation
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class AuthorizationOperations
{
  // #region Constants
  /**
   * Constant ROLE_LIST.
   *
   * List roles operation name.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string ROLE_LIST = 'role_list';

  /**
   * Constant ROLE_GET.
   *
   * Get role operation name.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string ROLE_GET = 'role_get';

  /**
   * Constant ROLE_CREATE.
   *
   * Create role operation name.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string ROLE_CREATE = 'role_create';

  /**
   * Constant ROLE_UPDATE.
   *
   * Update role operation name.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string ROLE_UPDATE = 'role_update';

  /**
   * Constant ROLE_DELETE.
   *
   * Delete role operation name.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string ROLE_DELETE = 'role_delete';

  /**
   * Constant ROLE_ADD_PERMISSION.
   *
   * Add permission to role operation name.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string ROLE_ADD_PERMISSION = 'role_add_permission';

  /**
   * Constant ROLE_REMOVE_PERMISSION.
   *
   * Remove permission from role operation name.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string ROLE_REMOVE_PERMISSION = 'role_remove_permission';

  /**
   * Constant PERMISSION_LIST.
   *
   * List permissions operation name.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string PERMISSION_LIST = 'permission_list';

  /**
   * Constant PERMISSION_GET.
   *
   * Get permission operation name.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string PERMISSION_GET = 'permission_get';

  /**
   * Constant PERMISSION_CREATE.
   *
   * Create permission operation name.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string PERMISSION_CREATE = 'permission_create';

  /**
   * Constant PERMISSION_UPDATE.
   *
   * Update permission operation name.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string PERMISSION_UPDATE = 'permission_update';

  /**
   * Constant PERMISSION_DELETE.
   *
   * Delete permission operation name.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string PERMISSION_DELETE = 'permission_delete';

  /**
   * Constant ROLE_OPERATIONS.
   *
   * Role operation names.
   *
   * @since 1.0.0
   *
   * @var list<string>
   */
  public const array ROLE_OPERATIONS = [
    self::ROLE_LIST,
    self::ROLE_GET,
    self::ROLE_CREATE,
    self::ROLE_UPDATE,
    self::ROLE_DELETE,
    self::ROLE_ADD_PERMISSION,
    self::ROLE_REMOVE_PERMISSION,
  ];

  /**
   * Constant PERMISSION_OPERATIONS.
   *
   * Permission operation names.
   *
   * @since 1.0.0
   *
   * @var list<string>
   */
  public const array PERMISSION_OPERATIONS = [
    self::PERMISSION_LIST,
    self::PERMISSION_GET,
    self::PERMISSION_CREATE,
    self::PERMISSION_UPDATE,
    self::PERMISSION_DELETE,
  ];

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
    ...self::ROLE_OPERATIONS,
    ...self::PERMISSION_OPERATIONS,
  ];
  // #endregion
}
