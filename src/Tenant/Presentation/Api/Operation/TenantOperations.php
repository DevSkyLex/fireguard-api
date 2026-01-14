<?php

declare(strict_types=1);

namespace Tenant\Presentation\Api\Operation;

/**
 * Tenant operation names.
 *
 * @category Operation
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class TenantOperations
{
  // #region Constants
  /**
   * Constant CREATE.
   *
   * Create tenant operation name.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string CREATE = 'tenant_create';

  /**
   * Constant GET.
   *
   * Get tenant operation name.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string GET = 'tenant_get';

  /**
   * Constant LIST.
   *
   * List tenants operation name.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string LIST = 'tenant_list';

  /**
   * Constant ALL.
   *
   * All tenant operation names.
   *
   * @since 1.0.0
   *
   * @var list<string>
   */
  public const array ALL = [
    self::CREATE,
    self::GET,
    self::LIST,
  ];
  // #endregion
}
