<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Operation;

/**
 * Class PasswordChangeOperations.
 *
 * @category Operation
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class PasswordChangeOperations
{
  // #region Constants
  /**
   * Constant REQUEST.
   *
   * Request password change (sends OTP) operation name.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string REQUEST = 'request_password_change';

  /**
   * Constant CONFIRM.
   *
   * Confirm password change (verifies OTP) operation name.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string CONFIRM = 'confirm_password_change';

  /**
   * Constant ALL.
   *
   * All password change operation names.
   *
   * @since 1.0.0
   *
   * @var list<string>
   */
  public const array ALL = [
    self::REQUEST,
    self::CONFIRM,
  ];
  // #endregion
}
