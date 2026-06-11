<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Serialization;

/**
 * Class AuthSerializationGroup.
 *
 * @category Serialization
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AuthSerializationGroup
{
  // #region Constants
  /**
   * Constant TOKEN_READ.
   *
   * Group TOKEN_READ
   * Used for reading token data.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string TOKEN_READ = 'token:read';

  /**
   * Constant TOKEN_WRITE.
   *
   * Group TOKEN_WRITE
   * Used for writing/creating token data.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string TOKEN_WRITE = 'token:write';

  /**
   * Constant PASSWORD_RESET_WRITE.
   *
   * Group PASSWORD_RESET_WRITE
   * Used for password reset operations.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string PASSWORD_RESET_WRITE = 'password_reset:write';

  /**
   * Constant PASSWORD_CHANGE_WRITE.
   *
   * Group PASSWORD_CHANGE_WRITE
   * Used for authenticated password change operations.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string PASSWORD_CHANGE_WRITE = 'password_change:write';
  // #endregion
}
