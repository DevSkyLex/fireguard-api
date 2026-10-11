<?php

declare(strict_types=1);

namespace Equipment\Domain\Exception;

use RuntimeException;

/** Media lookup failed without exposing another scope. @category Exception @version 1.0.0 @author Valentin FORTIN <contact@valentin-fortin.pro> */
final class EquipmentMediaNotFoundException extends RuntimeException
{
}
