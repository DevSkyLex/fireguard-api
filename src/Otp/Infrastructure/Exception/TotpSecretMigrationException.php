<?php

declare(strict_types=1);

namespace Otp\Infrastructure\Exception;

use RuntimeException;

/** A locked TOTP enrollment cannot be migrated or verified safely. */
final class TotpSecretMigrationException extends RuntimeException
{
}
