<?php

declare(strict_types=1);

namespace Otp\Infrastructure\Exception;

use RuntimeException;

/** Encryption configuration or envelope failure for persisted TOTP secrets. */
final class TotpSecretCipherException extends RuntimeException
{
}
