<?php

declare(strict_types=1);

namespace Otp\Presentation\Api\Operation;

/**
 * OTP operation names.
 *
 * @category Operation
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OtpOperations
{
  // #region Constants
  /**
   * Constant CREATE_CHALLENGE.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string CREATE_CHALLENGE = 'createChallenge';

  /**
   * Constant GET_CHALLENGE_STATUS.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string GET_CHALLENGE_STATUS = 'getChallengeStatus';

  /**
   * Constant VERIFY_CHALLENGE.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string VERIFY_CHALLENGE = 'verifyChallenge';

  /**
   * Constant RESEND_CHALLENGE.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string RESEND_CHALLENGE = 'resendChallenge';

  /**
   * Constant LIST_PURPOSES.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string LIST_PURPOSES = 'list_purposes';

  /**
   * Constant LIST_CHANNELS.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string LIST_CHANNELS = 'list_channels';

  /**
   * Constant SETUP_TOTP.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string SETUP_TOTP = 'setupTotp';

  /**
   * Constant ALL.
   *
   * @since 1.0.0
   *
   * @var list<string>
   */
  public const array ALL = [
    self::CREATE_CHALLENGE,
    self::GET_CHALLENGE_STATUS,
    self::VERIFY_CHALLENGE,
    self::RESEND_CHALLENGE,
    self::LIST_PURPOSES,
    self::LIST_CHANNELS,
    self::SETUP_TOTP,
  ];
  // #endregion
}
