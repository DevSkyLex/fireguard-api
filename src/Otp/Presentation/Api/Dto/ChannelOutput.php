<?php

declare(strict_types=1);

namespace Otp\Presentation\Api\Dto;

use ApiPlatform\Metadata\ApiProperty;

/**
 * DTO ChannelOutput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ChannelOutput
{
    // #region Properties
    /**
     * Property value.
     *
     * The channel identifier.
     */
    #[ApiProperty(
        description: 'The channel identifier (use this in API requests)',
        example: 'email',
    )]
    public string $value;

    /**
     * Property label.
     *
     * Human-readable label.
     */
    #[ApiProperty(
        description: 'Human-readable label for display',
        example: 'Email',
    )]
    public string $label;

    /**
     * Property requiresDelivery.
     *
     * Whether this channel requires active delivery.
     */
    #[ApiProperty(
        description: 'Whether OTP is actively delivered (false for TOTP which uses authenticator app)',
        example: true,
    )]
    public bool $requiresDelivery;
    // #endregion
}
