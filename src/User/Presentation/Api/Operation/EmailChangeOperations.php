<?php

declare(strict_types=1);

namespace User\Presentation\Api\Operation;

/**
 * Operation EmailChangeOperations.
 *
 * Operation name constants for the sign-in email change endpoints.
 *
 * @category Operation
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class EmailChangeOperations
{
  // #region Constants
  /**
   * Constant REQUEST.
   *
   * Request a sign-in email change (authenticated).
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string REQUEST = 'user_email_change_request';

  /**
   * Constant CONFIRM.
   *
   * Confirm a sign-in email change with the emailed token (public —
   * the token is the credential).
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string CONFIRM = 'user_email_change_confirm';

  /**
   * Constant CANCEL.
   *
   * Cancel the pending sign-in email change (authenticated).
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string CANCEL = 'user_email_change_cancel';

  /**
   * Constant ALL.
   *
   * Every email change operation name.
   *
   * @since 1.0.0
   *
   * @var list<string>
   */
  public const array ALL = [
    self::REQUEST,
    self::CONFIRM,
    self::CANCEL,
  ];
  // #endregion
}
