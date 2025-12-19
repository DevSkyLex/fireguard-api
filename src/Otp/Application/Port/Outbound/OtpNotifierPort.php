<?php

declare(strict_types=1);

namespace Otp\Application\Port\Outbound;

use Otp\Domain\Model\Otp;

/**
 * Port OtpNotifierPort.
 *
 * Outbound port for OTP delivery via notifications.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface OtpNotifierPort
{
    // #region Methods
    /**
     * Method send.
     *
     * Sends an OTP notification to the recipient.
     *
     * @param Otp $otp the OTP to send
     */
    public function send(Otp $otp): void;
    // #endregion
}
