<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Operation;

/**
 * Class RegistrationOperations.
 *
 * @category Operation
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RegistrationOperations
{
  // #region Constants
  /**
   * Register (create account + send verification code) operation.
   */
  public const string REGISTER = 'register_user';

  /**
   * Resend verification code operation.
   */
  public const string RESEND = 'resend_registration';

  /**
   * Confirm registration (verify email + auto-login) operation.
   */
  public const string CONFIRM = 'confirm_registration';
  // #endregion
}
