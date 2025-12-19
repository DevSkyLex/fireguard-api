<?php

declare(strict_types=1);

namespace Otp\Presentation\Api\Dto;

/**
 * DTO VerifyOtpOutput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class VerifyOtpOutput
{
    // #region Properties
    /**
     * Property success.
     *
     * Whether verification was successful.
     */
    public bool $success;

    /**
     * Property attemptsRemaining.
     *
     * Remaining verification attempts.
     */
    public int $attemptsRemaining;

    /**
     * Property error.
     *
     * Error message if failed.
     */
    public ?string $error = null;
    // #endregion
}
