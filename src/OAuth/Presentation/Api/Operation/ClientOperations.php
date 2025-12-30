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
  public const string CREATE = 'create';

  /**
   * Constant GET.
   *
   * Get operation name.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string GET = 'get';

  /**
   * Constant LIST.
   *
   * List operation name.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string LIST = 'list';

  /**
   * Constant UPDATE.
   *
   * Update operation name.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string UPDATE = 'update';

  /**
   * Constant REGENERATE_SECRET.
   *
   * Regenerate secret operation name.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string REGENERATE_SECRET = 'regenerate-secret';

  /**
   * Constant ACTIVATE.
   *
   * Activate operation name.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string ACTIVATE = 'activate';

  /**
   * Constant DEACTIVATE.
   *
   * Deactivate operation name.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string DEACTIVATE = 'deactivate';

  /**
   * Constant DELETE.
   *
   * Delete operation name.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string DELETE = 'delete';

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
