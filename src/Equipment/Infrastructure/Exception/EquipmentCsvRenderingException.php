<?php

declare(strict_types=1);

namespace Equipment\Infrastructure\Exception;

use RuntimeException;

/**
 * Class EquipmentCsvRenderingException
 *
 * Signals an equipment CSV stream failure before its artifact and receipt can commit.
 *
 * @category Exception
 */
final class EquipmentCsvRenderingException extends RuntimeException
{
}
