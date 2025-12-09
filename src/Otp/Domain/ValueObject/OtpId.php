<?php

declare(strict_types=1);

namespace Otp\Domain\ValueObject;

use Shared\Domain\ValueObject\Uuid;

/**
 * ValueObject OtpId
 * @final
 *
 * Represents a unique OTP identifier.
 *
 * @category ValueObject
 * @package Otp\Domain\ValueObject
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OtpId extends Uuid
{
}
