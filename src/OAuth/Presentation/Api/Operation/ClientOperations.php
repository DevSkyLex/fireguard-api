<?php

declare(strict_types=1);

namespace OAuth\Presentation\Api\Operation;

/**
 * Client operation names.
 *
 * @category Operation
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ClientOperations
{
  // #region Constants
  /**
   * Constant CREATE.
   *
   * Create operation name.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string CREATE = 'oauth_client_create';

  /**
   * Constant GET.
   *
   * Get operation name.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string GET = 'oauth_client_get';

  /**
   * Constant LIST.
   *
   * List operation name.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string LIST = 'oauth_client_list';

  /**
   * Constant UPDATE.
   *
   * Update operation name.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string UPDATE = 'oauth_client_update';

  /**
   * Constant REGENERATE_SECRET.
   *
   * Regenerate secret operation name.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string REGENERATE_SECRET = 'oauth_client_regenerate_secret';

  /**
   * Constant ACTIVATE.
   *
   * Activate operation name.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string ACTIVATE = 'oauth_client_activate';

  /**
   * Constant DEACTIVATE.
   *
   * Deactivate operation name.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string DEACTIVATE = 'oauth_client_deactivate';

  /**
   * Constant DELETE.
   *
   * Delete operation name.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string DELETE = 'oauth_client_delete';

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
    self::CREATE,
    self::GET,
    self::LIST,
    self::UPDATE,
    self::REGENERATE_SECRET,
    self::ACTIVATE,
    self::DEACTIVATE,
    self::DELETE,
  ];
  // #endregion
}
