<?php

declare(strict_types=1);

namespace Otp\Infrastructure\Exception;

use RuntimeException;

/**
 * Class OtpEmailDeliveryException
 *
 * Reports an OTP email delivery failure using redacted diagnostic information.
 *
 * @category Exception
 */
final class OtpEmailDeliveryException extends RuntimeException
{
}
