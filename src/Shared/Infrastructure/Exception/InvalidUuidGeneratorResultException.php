<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Exception;

use RuntimeException;

/** The UUID generator violated its return contract. */
final class InvalidUuidGeneratorResultException extends RuntimeException
{
}
