<?php

declare(strict_types=1);

namespace Otp\Application\Port\Outbound;

use Otp\Domain\Model\Otp;

/**
 * Port OtpNotifierPort
 *
 * Outbound port for OTP delivery via notifications.
 *
 * @category Port
 * @package Otp\Application\Port\Outbound
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface OtpNotifierPort
{
  //#region Methods
  /**
   * Method send
   *
   * Sends an OTP notification to the recipient.
   *
   * @param Otp $otp The OTP to send.
   *
   * @return void
   */
  public function send(Otp $otp): void;
  //#endregion
}
