<?php

declare(strict_types=1);

namespace Otp\Presentation\Api\Dto;

use DateTimeImmutable;

/**
 * DTO OtpOutput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OtpOutput
{
    // #region Properties
    /**
     * Property id.
     *
     * The OTP ID.
     */
    public string $id;

    /**
     * Property status.
     *
     * The OTP status (pending, verified, expired, failed).
     */
    public string $status;

    /**
     * Property maskedRecipient.
     *
     * The masked recipient.
     */
    public string $maskedRecipient;

    /**
     * Property expiresAt.
     *
     * When the OTP expires.
     */
    public DateTimeImmutable $expiresAt;

    /**
     * Property attemptsRemaining.
     *
     * Remaining verification attempts.
     */
    public int $attemptsRemaining;
    // #endregion
}
