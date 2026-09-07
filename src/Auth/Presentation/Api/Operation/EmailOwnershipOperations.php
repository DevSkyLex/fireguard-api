<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Operation;

/**
 * Operation EmailOwnershipOperations.
 *
 * @category Operation
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class EmailOwnershipOperations
{
  // #region Constants
  /**
   * @since 1.0.0
   */
  public const string GET = 'auth_email_ownership_get';

  /**
   * @since 1.0.0
   */
  public const string START = 'auth_email_ownership_start';

  /**
   * @since 1.0.0
   */
  public const string CONFIRM = 'auth_email_ownership_confirm';

  /**
   * @since 1.0.0
   */
  public const string READ = 'email_ownership:read';

  /**
   * @since 1.0.0
   */
  public const string WRITE = 'email_ownership:write';
  // #endregion
}
