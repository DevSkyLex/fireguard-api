<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Operation;

/**
 * Class PasswordResetOperations.
 *
 * @category Operation
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class PasswordResetOperations
{
  // #region Constants
  /**
   * Request password reset operation.
   */
  public const string REQUEST = 'request_password_reset';

  /**
   * Resend password reset operation.
   */
  public const string RESEND = 'resend_password_reset';

  /**
   * Confirm password reset operation.
   */
  public const string CONFIRM = 'confirm_password_reset';
  // #endregion
}
